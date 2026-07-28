<?php

namespace App\Models;

use App\Enums\IssuedCertificateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class IssuedCertificate extends Model
{
    private const IMMUTABLE_ATTRIBUTES = [
        'certificate_number',
        'request_key',
        'training_id',
        'employee_id',
        'certificate_template_id',
        'supersedes_id',
        'template_snapshot',
        'participant_snapshot',
        'variables_snapshot',
        'issued_on',
        'expires_at',
        'issued_by',
    ];

    protected $fillable = [
        'certificate_number',
        'request_key',
        'training_id',
        'employee_id',
        'certificate_template_id',
        'supersedes_id',
        'status',
        'template_snapshot',
        'participant_snapshot',
        'variables_snapshot',
        'issued_on',
        'expires_at',
        'pdf_disk',
        'pdf_path',
        'pdf_checksum',
        'pdf_bytes',
        'issued_by',
        'processing_started_at',
        'issued_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'failure_message',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $certificate): void {
            foreach (self::IMMUTABLE_ATTRIBUTES as $attribute) {
                if ($certificate->isDirty($attribute)) {
                    throw new LogicException(
                        "Issued certificate {$attribute} is immutable."
                    );
                }
            }
        });

        static::deleting(function (self $certificate): void {
            throw new LogicException(
                'Issued certificates cannot be deleted.'
            );
        });
    }

    protected function casts(): array
    {
        return [
            'status' => IssuedCertificateStatus::class,
            'template_snapshot' => 'array',
            'participant_snapshot' => 'array',
            'variables_snapshot' => 'array',
            'issued_on' => 'date',
            'expires_at' => 'date',
            'processing_started_at' => 'datetime',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'pdf_bytes' => 'integer',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(
            CertificateTemplate::class
        )->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by')->withTrashed();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_id'
        );
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(
            self::class,
            'supersedes_id'
        );
    }
}
