<?php

namespace App\Jobs\Certificates;

use App\Contracts\Certificates\CertificatePdfRenderer;
use App\Enums\IssuedCertificateStatus;
use App\Models\IssuedCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateIssuedCertificatePdf implements
    ShouldQueue,
    ShouldBeUnique,
    ShouldBeEncrypted
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $issuedCertificateId
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->issuedCertificateId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'issued-certificate-'.$this->issuedCertificateId
            ))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(
        CertificatePdfRenderer $renderer
    ): void {
        $certificate = DB::transaction(function ():
            ?IssuedCertificate {
            $certificate = IssuedCertificate::query()
                ->lockForUpdate()
                ->find($this->issuedCertificateId);

            if ($certificate === null) {
                return null;
            }

            if (
                in_array(
                    $certificate->status,
                    [
                        IssuedCertificateStatus::ISSUED,
                        IssuedCertificateStatus::REVOKED,
                    ],
                    true
                )
            ) {
                return null;
            }

            if (
                ! in_array(
                    $certificate->status,
                    [
                        IssuedCertificateStatus::PENDING,
                        IssuedCertificateStatus::PROCESSING,
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Certificate is not ready for PDF generation.'
                );
            }

            $certificate->update([
                'status' => IssuedCertificateStatus::PROCESSING,
                'processing_started_at' => now(),
                'failure_message' => null,
            ]);

            return $certificate->fresh();
        }, 3);

        if ($certificate === null) {
            return;
        }

        $document = $renderer->render($certificate);
        $diskName = (string) config(
            'certificates.storage.pdf_disk',
            'local'
        );
        $directory = trim(
            (string) config(
                'certificates.storage.pdf_directory',
                'certificates/issued'
            ),
            '/'
        );
        $year = $certificate->issued_on->format('Y');
        $safeNumber = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $certificate->certificate_number
        ) ?: 'certificate-'.$certificate->id;
        $filename = $safeNumber.'.pdf';
        $finalPath = $directory.'/'.$year.'/'.$filename;
        $temporaryPath = $finalPath.'.tmp-'.Str::uuid();
        $storage = Storage::disk($diskName);
        $finalMoved = false;

        try {
            if (! $storage->put($temporaryPath, $document->contents)) {
                throw new RuntimeException(
                    'Failed to write the temporary certificate PDF.'
                );
            }

            if (
                $storage->size($temporaryPath) !== $document->bytes
                || ! hash_equals(
                    $document->checksum,
                    hash('sha256', $storage->get($temporaryPath))
                )
            ) {
                throw new RuntimeException(
                    'Temporary certificate PDF integrity check failed.'
                );
            }

            if ($storage->exists($finalPath)) {
                $storage->delete($finalPath);
            }

            if (! $storage->move($temporaryPath, $finalPath)) {
                throw new RuntimeException(
                    'Failed to move the certificate PDF into place.'
                );
            }

            $finalMoved = true;

            DB::transaction(function () use (
                $certificate,
                $diskName,
                $finalPath,
                $document
            ): void {
                $locked = IssuedCertificate::query()
                    ->lockForUpdate()
                    ->findOrFail($certificate->id);

                if (
                    $locked->status
                    !== IssuedCertificateStatus::PROCESSING
                ) {
                    throw new RuntimeException(
                        'Certificate status changed during PDF generation.'
                    );
                }

                $locked->update([
                    'status' => IssuedCertificateStatus::ISSUED,
                    'pdf_disk' => $diskName,
                    'pdf_path' => $finalPath,
                    'pdf_checksum' => $document->checksum,
                    'pdf_bytes' => $document->bytes,
                    'issued_at' => now(),
                    'processing_started_at' => null,
                    'failure_message' => null,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if ($storage->exists($temporaryPath)) {
                $storage->delete($temporaryPath);
            }

            if ($finalMoved && $storage->exists($finalPath)) {
                $storage->delete($finalPath);
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        IssuedCertificate::query()
            ->whereKey($this->issuedCertificateId)
            ->whereIn('status', [
                IssuedCertificateStatus::PENDING->value,
                IssuedCertificateStatus::PROCESSING->value,
            ])
            ->update([
                'status' => IssuedCertificateStatus::FAILED->value,
                'processing_started_at' => null,
                'failure_message' =>
                    'PDF generation failed after queue retries.',
                'updated_at' => now(),
            ]);
    }
}
