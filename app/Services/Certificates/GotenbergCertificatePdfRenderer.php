<?php

namespace App\Services\Certificates;

use App\Contracts\Certificates\CertificatePdfRenderer;
use App\Data\Certificates\CertificatePdfDocument;
use App\Models\IssuedCertificate;
use App\Support\Certificates\CertificateTemplateSnapshotValidator;
use App\Support\Certificates\CertificateVariableRenderer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use RuntimeException;

final class GotenbergCertificatePdfRenderer implements CertificatePdfRenderer
{
    public function __construct(
        private readonly CertificateTemplateSnapshotValidator $snapshotValidator,
        private readonly CertificateVariableRenderer $variableRenderer,
    ) {
    }

    public function render(
        IssuedCertificate $certificate
    ): CertificatePdfDocument {
        $template = $certificate->template_snapshot;
        $variables = $certificate->variables_snapshot;

        if (! is_array($template) || ! is_array($variables)) {
            throw new RuntimeException(
                'Certificate snapshots are incomplete.'
            );
        }

        $this->snapshotValidator->validate($template);

        $compiledCss = Vite::content(
            (string) config(
                'certificates.pdf.css_entry',
                'resources/css/app.css'
            )
        );

        $html = view('certificates.pdf', [
            'certificate' => $certificate,
            'template' => $template,
            'variables' => $variables,
            'compiledCss' => $compiledCss,
            'backgroundDataUri' =>
                $this->backgroundDataUri($template),
            'renderText' => fn (?string $text): string =>
                $this->variableRenderer->render(
                    (string) $text,
                    $variables
                ),
        ])->render();

        $response = $this->requestPdf(
            $html,
            $certificate->request_key,
            $certificate->certificate_number
        );

        return CertificatePdfDocument::fromContents(
            $response->body()
        );
    }

    private function requestPdf(
        string $html,
        string $trace,
        string $outputFilename
    ): Response {
        $baseUrl = (string) config(
            'certificates.gotenberg.url'
        );
        $endpoint = (string) config(
            'certificates.gotenberg.endpoint',
            '/forms/chromium/convert/html'
        );
        $attempts = max(
            1,
            (int) config('certificates.gotenberg.retries', 2) + 1
        );
        $delayMs = max(
            0,
            (int) config(
                'certificates.gotenberg.retry_delay_ms',
                250
            )
        );
        $lastResponse = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::connectTimeout(
                    (int) config(
                        'certificates.gotenberg.connect_timeout',
                        5
                    )
                )
                    ->timeout(
                        (int) config(
                            'certificates.gotenberg.timeout',
                            60
                        )
                    )
                    ->accept('application/pdf')
                    ->withHeaders([
                        'Gotenberg-Trace' => $trace,
                        'Gotenberg-Output-Filename' =>
                            $outputFilename,
                    ])
                    ->attach(
                        'files',
                        $html,
                        'index.html',
                        ['Content-Type' => 'text/html; charset=UTF-8']
                    )
                    ->post($baseUrl.$endpoint, [
                        'printBackground' => 'true',
                        'preferCssPageSize' => 'true',
                        'emulatedMediaType' => 'screen',
                        'marginTop' => '0',
                        'marginBottom' => '0',
                        'marginLeft' => '0',
                        'marginRight' => '0',
                        'waitForExpression' => (string) config(
                            'certificates.gotenberg.wait_for_expression',
                            'window.certificateReady === true'
                        ),
                    ]);

                if ($response->successful()) {
                    $contentType = strtolower(
                        (string) $response->header('Content-Type')
                    );

                    if (
                        $contentType !== ''
                        && ! str_contains(
                            $contentType,
                            'application/pdf'
                        )
                    ) {
                        throw new RuntimeException(
                            'Gotenberg returned an unexpected content type.'
                        );
                    }

                    return $response;
                }

                $lastResponse = $response;

                if (! $response->serverError()) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }

            if ($attempt < $attempts && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        if ($lastException !== null && $lastResponse === null) {
            throw new RuntimeException(
                'Unable to connect to the Gotenberg PDF service.',
                previous: $lastException
            );
        }

        throw new RuntimeException(sprintf(
            'Gotenberg failed to render the certificate PDF (HTTP %s).',
            $lastResponse?->status() ?? 'unknown'
        ));
    }

    /**
     * @param array<string, mixed> $template
     */
    private function backgroundDataUri(array $template): ?string
    {
        $background = $template['background'] ?? null;

        if (! is_array($background)) {
            return null;
        }

        $contents = Storage::disk(
            (string) $background['disk']
        )->get((string) $background['path']);

        return sprintf(
            'data:%s;base64,%s',
            (string) $background['mime_type'],
            base64_encode($contents)
        );
    }
}
