<?php

namespace App\Enums;

enum IssuedCertificateStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case ISSUED = 'issued';
    case FAILED = 'failed';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::ISSUED => 'Issued',
            self::FAILED => 'Failed',
            self::REVOKED => 'Revoked',
        };
    }

    public function canDownload(): bool
    {
        return $this === self::ISSUED;
    }
}
