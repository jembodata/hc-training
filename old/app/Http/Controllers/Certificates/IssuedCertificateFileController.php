<?php

namespace App\Http\Controllers\Certificates;

use App\Enums\IssuedCertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\IssuedCertificate;
use App\Support\Auth\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssuedCertificateFileController extends Controller
{
    public function preview(
        IssuedCertificate $issuedCertificate
    ): StreamedResponse {
        return $this->fileResponse(
            $issuedCertificate,
            'inline'
        );
    }

    public function download(
        IssuedCertificate $issuedCertificate
    ): StreamedResponse {
        return $this->fileResponse(
            $issuedCertificate,
            'attachment'
        );
    }

    private function fileResponse(
        IssuedCertificate $certificate,
        string $disposition
    ): StreamedResponse {
        Gate::authorize(Permissions::DOWNLOAD_CERTIFICATE);

        abort_unless(
            $certificate->status
                === IssuedCertificateStatus::ISSUED,
            404
        );

        abort_if(
            empty($certificate->pdf_disk)
            || empty($certificate->pdf_path)
            || empty($certificate->pdf_checksum),
            404
        );

        $expectedDisk = (string) config(
            'certificates.storage.pdf_disk',
            'local'
        );
        $expectedDirectory = trim(
            (string) config(
                'certificates.storage.pdf_directory',
                'certificates/issued'
            ),
            '/'
        ).'/';

        abort_unless(
            $certificate->pdf_disk === $expectedDisk
            && str_starts_with(
                $certificate->pdf_path,
                $expectedDirectory
            )
            && ! str_contains($certificate->pdf_path, '..')
            && ! str_contains($certificate->pdf_path, '\\'),
            404
        );

        $storage = Storage::disk($certificate->pdf_disk);

        abort_unless($storage->exists($certificate->pdf_path), 404);

        $contents = $storage->get($certificate->pdf_path);
        $checksum = hash('sha256', $contents);

        abort_unless(
            hash_equals($certificate->pdf_checksum, $checksum),
            409,
            'Certificate PDF integrity verification failed.'
        );

        abort_unless(
            strlen($contents) === (int) $certificate->pdf_bytes,
            409,
            'Certificate PDF size verification failed.'
        );

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $certificate->certificate_number
        ) ?: 'certificate-'.$certificate->id;
        $filename = $safeNumber.'.pdf';

        return response()->stream(
            static function () use ($contents): void {
                echo $contents;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($contents),
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $disposition,
                    addcslashes($filename, '"\\')
                ),
                'Cache-Control' =>
                    'private, no-store, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
