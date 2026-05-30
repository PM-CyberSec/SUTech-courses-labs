<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'inventory_id',
        'config_template_id',
        'requested_by',
        'playbook_name',
        'status',
        'precheck_status',
        'postcheck_status',
        'is_idempotent',
        'variables',
        'validation_results',
        'simulation_mode',
        'generated_config',
        'rendered_config_path',
        'ansible_command',
        'output',
        'errors',
        'started_at',
        'finished_at',
        'executed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'validation_results' => 'array',
            'is_idempotent' => 'boolean',
            'simulation_mode' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function configTemplate(): BelongsTo
    {
        return $this->belongsTo(ConfigTemplate::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class);
    }

    public function rollbacks(): HasMany
    {
        return $this->hasMany(Rollback::class);
    }

    public function configSnapshots(): HasMany
    {
        return $this->hasMany(ConfigSnapshot::class);
    }
}
