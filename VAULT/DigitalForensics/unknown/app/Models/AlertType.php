<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertType extends Model
{
    protected $table = 'alert_types';

    protected $fillable = [
        'name',
    ];
}
