<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DeviceInterface extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_device_id',
        'name',
        'mode',
        'ip_address',
        'subnet_mask',
        'vlan_id',
        'native_vlan',
        'allowed_vlans',
        'description',
        'is_shutdown',
    ];

    protected function casts(): array
    {
        return [
            'is_shutdown' => 'boolean',
        ];
    }

    public function topologyDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class);
    }
}
