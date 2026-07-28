<?php

namespace App\Support\Certificates;

use App\Models\CertificateNumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CertificateNumberGenerator
{
    public function next(?int $year = null): string
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException(
                'Certificate number generation must run inside a database transaction.'
            );
        }

        $year ??= (int) now()->format('Y');
        $now = now();

        CertificateNumberSequence::query()->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = CertificateNumberSequence::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->last_number++;
        $sequence->save();

        $prefix = preg_replace(
            '/[^A-Z0-9]+/',
            '-',
            strtoupper(
                trim((string) config(
                    'certificates.number.prefix',
                    'CERT'
                ))
            )
        );
        $prefix = trim((string) $prefix, '-');
        $prefix = $prefix === ''
            ? 'CERT'
            : substr($prefix, 0, 24);

        $digits = min(
            12,
            max(
                1,
                (int) config('certificates.number.digits', 6)
            )
        );

        return sprintf(
            '%s-%d-%0'.$digits.'d',
            $prefix,
            $year,
            $sequence->last_number
        );
    }
}
