<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopologyConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_id',
        'topology_device_id',
        'config_type',
        'generated_cli',
        'validation_status',
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

    public function topologyDevice(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class);
    }
}