<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $fillable = [
        'org_name',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'organization_id', 'id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'org_id', 'id');
    }
}