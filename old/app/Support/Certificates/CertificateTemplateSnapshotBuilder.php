<?php

namespace App\Support\Certificates;

use App\Models\CertificateTemplate;
use App\Rules\ValidCertificateLayout;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CertificateTemplateSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(CertificateTemplate $template): array
    {
        if ($template->trashed() || $template->isArchived()) {
            throw ValidationException::withMessages([
                'template' => 'Certificate template harus aktif.',
            ]);
        }

        $layout = is_array($template->layout_settings)
            ? $template->layout_settings
            : null;

        $validated = Validator::make(
            ['layout' => $layout],
            ['layout' => ['required', new ValidCertificateLayout()]]
        )->validate();

        return [
            'schema_version' => 1,
            'template_id' => (int) $template->id,
            'name' => (string) $template->name,
            'kind' => (string) $template->kind,
            'design' => (string) $template->design,
            'header_text' => (string) $template->header_text,
            'body_text' => (string) $template->body_text,
            'signature_line' => $template->signature_line,
            'digital_signature_enabled' =>
                (bool) $template->digital_signature_enabled,
            'signature_provider' => $template->signature_provider,
            'signer_label' => $template->signer_label,
            'signer_position' => $template->signer_position,
            'layout_settings' =>
                CertificateLayoutSchema::normalizeValidated(
                    $validated['layout']
                ),
            'background' => $this->snapshotBackground($template),
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotBackground(
        CertificateTemplate $template
    ): ?array {
        if (
            $template->design
            !== CertificateTemplate::DESIGN_CUSTOM_UPLOAD
        ) {
            return null;
        }

        $sourcePath = ltrim(
            (string) $template->custom_background_path,
            '/'
        );

        $sourcePath = preg_replace(
            '#^storage/#',
            '',
            $sourcePath
        ) ?? $sourcePath;

        if ($sourcePath === '') {
            throw ValidationException::withMessages([
                'template' => 'Background custom template tidak ditemukan.',
            ]);
        }

        $sourceDiskName = (string) config(
            'certificates.storage.template_background_disk',
            'public'
        );
        $snapshotDiskName = (string) config(
            'certificates.storage.snapshot_background_disk',
            'local'
        );

        $sourceDisk = Storage::disk($sourceDiskName);

        if (! $sourceDisk->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'template' => 'File background certificate template tidak ditemukan.',
            ]);
        }

        $contents = $sourceDisk->get($sourcePath);
        $checksum = hash('sha256', $contents);
        $mimeType = $this->detectMimeType($contents);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages([
                'template' => 'Format background certificate template tidak didukung.',
            ]),
        };

        $directory = trim(
            (string) config(
                'certificates.storage.snapshot_background_directory',
                'certificates/template-backgrounds'
            ),
            '/'
        );
        $snapshotPath = $directory.'/'.$checksum.'.'.$extension;
        $snapshotDisk = Storage::disk($snapshotDiskName);

        if (
            ! $snapshotDisk->exists($snapshotPath)
            && ! $snapshotDisk->put($snapshotPath, $contents)
        ) {
            throw new RuntimeException(
                'Failed to persist the certificate background snapshot.'
            );
        }

        return [
            'disk' => $snapshotDiskName,
            'path' => $snapshotPath,
            'checksum' => $checksum,
            'bytes' => strlen($contents),
            'mime_type' => $mimeType,
        ];
    }

    private function detectMimeType(string $contents): string
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $fileInfo->buffer($contents);
    }
}
