<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use SoftDeletes;

    public const KIND_COMPLETION = 'completion';
    public const KIND_PARTICIPATION = 'participation';

    public const DESIGN_MINIMAL_ACADEMIC = 'minimal_academic';
    public const DESIGN_MODERN_BLUE = 'modern_blue';
    public const DESIGN_CUSTOM_UPLOAD = 'custom_upload';

    public const SUPPORTED_VARIABLES = [
        'employee_name',
        'employee_nik',
        'department_name',
        'position_name',
        'course_title',
        'tanggal_training',
        'training_group_title',
        'training_group_code',
        'batch_number',
        'batch_name',
        'held_by',
        'training_start_time',
        'training_finish_time',
        'certificate_id',
        'issued_on',
        'expires_at',
    ];

    protected $fillable = [
        'name',
        'kind',
        'design',
        'custom_background_path',
        'font_family',
        'title_font_family',
        'header_text',
        'body_text',
        'signature_line',
        'digital_signature_enabled',
        'signature_provider',
        'signer_label',
        'signer_position',
        'signature_layout',
        'layout_settings',
        'is_default',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'digital_signature_enabled' => 'boolean',
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
            'layout_settings' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeForKind(
        Builder $query,
        string $kind
    ): Builder {
        return $query->where('kind', $kind);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(
            IssuedCertificate::class
        );
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(
            Training::class,
            'certificate_template_id'
        );
    }
}
