<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DldsEvent extends Model
{
    protected $table = 'dlds_events';

    protected $fillable = [
        'timestamp',
        'type',
        'pid',
        'process_name',
        'file',
        'src_ip',
        'src_port',
        'dst_ip',
        'dst_port',
        'bytes_sent',
        'alert_type',
        'severity',
        'description'
    ];
}