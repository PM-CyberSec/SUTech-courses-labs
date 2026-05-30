<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'hostname',
        'mgmt_ip',
        'ansible_host',
        'ssh_port',
        'platform',
        'vendor',
        'auth_username',
        'auth_password',
        'become_password',
        'connection',
        'status',
        'metadata',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function hostVariables(): HasMany
    {
        return $this->hasMany(HostVariable::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function configSnapshots(): HasMany
    {
        return $this->hasMany(ConfigSnapshot::class);
    }
}
