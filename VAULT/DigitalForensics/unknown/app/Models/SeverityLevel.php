<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeverityLevel extends Model
{
    protected $table = 'severity_levels';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
