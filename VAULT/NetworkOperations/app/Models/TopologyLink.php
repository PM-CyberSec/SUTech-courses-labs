<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TopologyLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_id',
        'from_topology_device_id',
        'to_topology_device_id',
        'from_interface_name',
        'to_interface_name',
        'link_type',
        'vlan_id',
        'allowed_vlans',
        'source_device_id',
        'source_interface',
        'target_device_id',
        'target_interface',
        'cable_type',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function topology(): BelongsTo
    {
        return $this->belongsTo(Topology::class);
    }

    public function fromDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class, 'from_topology_device_id');
    }

    public function toDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class, 'to_topology_device_id');
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class, 'target_device_id');
    }
}
