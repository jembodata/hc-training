<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Training extends Model
{
    use SoftDeletes;

    protected $table = 'trainings';

    protected $fillable = [
        'training_group_id',
        'batch_number',
        'batch_name',
        'title',
        'held_by',
        'is_certified',
        'certificate_template_id',
        'activity_name',
        'skill_name',
        'trainer_employee_id',
        'trainer_external_name',
        'training_date',
        'start_time',
        'finish_time',
        'fee',
    ];

    protected $casts = [
        'training_date' => 'date',
        'fee' => 'decimal:2',
    ];

    public function participantRecords(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'training_id', 'id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'training_participants', 'training_id', 'employee_id')
            ->withPivot('id', 'score')
            ->withTimestamps();
    }

    public function trainerInternal(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'trainer_employee_id', 'id');
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this
            ->belongsTo(
                CertificateTemplate::class,
                'certificate_template_id'
            )
            ->withTrashed();
    }
}
