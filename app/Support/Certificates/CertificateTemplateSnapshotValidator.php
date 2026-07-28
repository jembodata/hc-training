<?php

namespace App\Support\Certificates;

use App\Models\CertificateTemplate;
use App\Rules\ValidCertificateLayout;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class CertificateTemplateSnapshotValidator
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function validate(array $snapshot): void
    {
        Validator::make($snapshot, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'template_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => [
                'required',
                'string',
                'in:' . implode(',', [
                    CertificateTemplate::KIND_COMPLETION,
                    CertificateTemplate::KIND_PARTICIPATION,
                ]),
            ],
            'design' => [
                'required',
                'string',
                'in:' . implode(',', [
                    CertificateTemplate::DESIGN_MINIMAL_ACADEMIC,
                    CertificateTemplate::DESIGN_MODERN_BLUE,
                    CertificateTemplate::DESIGN_CUSTOM_UPLOAD,
                ]),
            ],
            'header_text' => ['present', 'string'],
            'body_text' => ['required', 'string'],
            'signature_line' => ['nullable', 'string'],
            'digital_signature_enabled' => ['required', 'boolean'],
            'signature_provider' => ['nullable', 'string'],
            'signer_label' => ['nullable', 'string'],
            'signer_position' => ['nullable', 'string'],
            'layout_settings' => [
                'required',
                new ValidCertificateLayout(),
            ],
            'background' => ['nullable', 'array'],
        ])->validate();

        $this->validateVariables($snapshot);

        if (
            $snapshot['design']
            !== CertificateTemplate::DESIGN_CUSTOM_UPLOAD
        ) {
            return;
        }

        $background = $snapshot['background'] ?? null;

        if (! is_array($background)) {
            throw new RuntimeException(
                'Custom certificate background snapshot is missing.'
            );
        }

        $disk = (string) ($background['disk'] ?? '');
        $path = (string) ($background['path'] ?? '');
        $expectedChecksum = (string) (
            $background['checksum'] ?? ''
        );

        $expectedDisk = (string) config(
            'certificates.storage.snapshot_background_disk',
            'local'
        );
        $expectedDirectory = trim(
            (string) config(
                'certificates.storage.snapshot_background_directory',
                'certificates/template-backgrounds'
            ),
            '/'
        ).'/';
        $expectedPathPattern = '#^'
            .preg_quote($expectedDirectory, '#')
            .'[a-f0-9]{64}\.(jpg|png)$#';
        $expectedBytes = (int) ($background['bytes'] ?? 0);
        $mimeType = (string) ($background['mime_type'] ?? '');

        if (
            $disk === ''
            || $path === ''
            || $expectedChecksum === ''
            || $expectedBytes < 1
            || ! in_array(
                $mimeType,
                ['image/jpeg', 'image/png'],
                true
            )
            || $disk !== $expectedDisk
            || ! preg_match($expectedPathPattern, $path)
            || ! preg_match('/^[a-f0-9]{64}$/', $expectedChecksum)
        ) {
            throw new RuntimeException(
                'Custom certificate background snapshot is incomplete.'
            );
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new RuntimeException(
                'Custom certificate background snapshot file is missing.'
            );
        }

        $contents = $storage->get($path);
        $actualChecksum = hash('sha256', $contents);

        if (
            strlen($contents) !== $expectedBytes
            || ! hash_equals($expectedChecksum, $actualChecksum)
        ) {
            throw new RuntimeException(
                'Custom certificate background snapshot checksum mismatch.'
            );
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function validateVariables(array $snapshot): void
    {
        foreach (
            [
                'header_text',
                'body_text',
                'signature_line',
                'signer_label',
                'signer_position',
            ] as $field
        ) {
            preg_match_all(
                '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
                (string) ($snapshot[$field] ?? ''),
                $matches
            );

            $unknown = array_diff(
                array_unique(
                    array_map(
                        'strtolower',
                        $matches[1] ?? []
                    )
                ),
                CertificateTemplate::SUPPORTED_VARIABLES
            );

            if ($unknown !== []) {
                throw new RuntimeException(
                    'Unsupported certificate variable: '
                        .implode(', ', $unknown).'.'
                );
            }
        }
    }
}
