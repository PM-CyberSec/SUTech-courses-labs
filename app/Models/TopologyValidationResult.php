<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopologyValidationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'topology_id',
        'severity',
        'category',
        'message',
        'device_id',
        'link_id',
        'suggested_fix',
    ];

    public function topology(): BelongsTo
    {
        return $this->belongsTo(Topology::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TopologyDevice::class, 'device_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(TopologyLink::class, 'link_id');
    }
}