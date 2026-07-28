<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'org_id',
        'position_id',
        'nik',
        'name',
        'status',
        'status_employee',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

    public function trainingParticipants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'employee_id', 'id');
    }

    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'training_participants', 'employee_id', 'training_id')
            ->withPivot('id', 'score')
            ->withTimestamps();
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }

    public function trainingsAsTrainer(): HasMany
    {
        return $this->hasMany(Training::class, 'trainer_employee_id', 'id');
    }
}