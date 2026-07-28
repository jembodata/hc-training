<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class training_schedules extends Model
{
    protected $fillable = [
        'training_date',
        'start_time',
        'end_time',
    ];  

    
    public function schedules(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }
}
