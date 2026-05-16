<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class TopologyDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_id',
        'hostname',
        'device_type',
        'name',
        'type',
        'model',
        'role',
        'x_position',
        'y_position',
        'enable_secret',
        'console_password',
        'vty_password',
        'service_password_encryption',
        'routing_protocol',
        'default_gateway',
        'vlans',
        'static_routes',
        'dhcp_pools',
        'nat_rules',
        'acl_rules',
        'ssh_settings',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'service_password_encryption' => 'boolean',
            'vlans' => 'array',
            'static_routes' => 'array',
            'dhcp_pools' => 'array',
            'nat_rules' => 'array',
            'acl_rules' => 'array',
            'ssh_settings' => 'array',
            'metadata' => 'array',
        ];
    }

    public function topology(): BelongsTo
    {
        return $this->belongsTo(Topology::class);
    }

    /**
     * Returns the primary interfaces for topology devices.
     * This returns TopologyInterface (AI-generated) for consistency.
     * For manual DeviceInterface creation, use deviceInterfaces().
     *
     * @return HasMany<TopologyInterface>
     */
    public function interfaces(): HasMany
    {
        return $this->hasMany(TopologyInterface::class);
    }

    /**
     * Returns DeviceInterface records (used by manual topology creation).
     *
     * @return HasMany<DeviceInterface>
     */
    public function deviceInterfaces(): HasMany
    {
        return $this->hasMany(DeviceInterface::class);
    }

    /**
     * Alias for interfaces() for backward compatibility.
     *
     * @return HasMany<TopologyInterface>
     */
    public function topologyInterfaces(): HasMany
    {
        return $this->hasMany(TopologyInterface::class);
    }

    /**
     * Returns all interfaces (both TopologyInterface and DeviceInterface).
     * This provides a unified view for templates and tests.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function allInterfaces(): \Illuminate\Database\Eloquent\Collection
    {
        $topologyInterfaces = $this->hasMany(TopologyInterface::class)->get();
        $deviceInterfaces = $this->hasMany(DeviceInterface::class)->get();

        // Merge and deduplicate by name
        return $topologyInterfaces->merge($deviceInterfaces)->unique(function ($item) {
            return $item->name;
        })->values();
    }

    public function configs(): HasMany
    {
        return $this->hasMany(TopologyConfig::class);
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(TopologyLink::class, 'from_topology_device_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(TopologyLink::class, 'to_topology_device_id');
    }

    public function generatedConfig(): HasOne
    {
        return $this->hasOne(GeneratedConfig::class);
    }
}
