<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'created_by',
    ];

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class)
            ->orderBy('batch_number');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}