<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class GeneratedConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_id',
        'topology_device_id',
        'config_text',
        'config_hash',
        'validation_errors',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_errors' => 'array',
            'generated_at' => 'datetime',
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
