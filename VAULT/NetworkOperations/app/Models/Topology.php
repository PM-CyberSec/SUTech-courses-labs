<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Topology extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'default_routing_protocol',
        'scenario_type',
        'created_by',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function topologyDevices(): HasMany
    {
        return $this->hasMany(TopologyDevice::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(TopologyDevice::class);
    }

    public function topologyLinks(): HasMany
    {
        return $this->hasMany(TopologyLink::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(TopologyLink::class);
    }

    public function configs(): HasMany
    {
        return $this->hasMany(TopologyConfig::class);
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(TopologyValidationResult::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedConfigs(): HasMany
    {
        return $this->hasMany(GeneratedConfig::class);
    }
}
