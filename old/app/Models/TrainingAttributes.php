<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingAttributes extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'is_active',
    ];

    public function scopeActivities($query)
    {
        return $query->where('type', 'activity');
    }

    public function scopeSkills($query)
    {
        return $query->where('type', 'skill');
    }
}
