<?php

namespace App\Data\Certificates;

use RuntimeException;

final readonly class CertificatePdfDocument
{
    private function __construct(
        public string $contents,
        public int $bytes,
        public string $checksum
    ) {
    }

    public static function fromContents(string $contents): self
    {
        $bytes = strlen($contents);
        $minimumBytes = (int) config(
            'certificates.pdf.minimum_bytes',
            5000
        );

        if (! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException(
                'The PDF renderer returned an invalid document.'
            );
        }

        if ($bytes < $minimumBytes) {
            throw new RuntimeException(
                'The rendered certificate PDF is unexpectedly small.'
            );
        }

        return new self(
            contents: $contents,
            bytes: $bytes,
            checksum: hash('sha256', $contents),
        );
    }
}
