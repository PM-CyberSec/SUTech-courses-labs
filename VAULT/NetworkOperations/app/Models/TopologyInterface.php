<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopologyInterface extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_device_id',
        'name',
        'type',
        'ip_address',
        'subnet_mask',
        'vlan_id',
        'mode',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function topologyDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class);
    }
}