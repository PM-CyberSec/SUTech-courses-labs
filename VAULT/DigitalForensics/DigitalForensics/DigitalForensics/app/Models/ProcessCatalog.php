<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessCatalog extends Model
{
    protected $table = 'process_catalog';

    protected $fillable = [
        'process_name',
    ];
}
