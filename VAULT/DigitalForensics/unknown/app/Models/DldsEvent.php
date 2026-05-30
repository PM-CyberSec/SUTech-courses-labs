<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class DldsEvent extends Model
{
    protected $table = 'dlds_events';

    protected $fillable = [
        'event_time',
        'event_type_id',
        'pid',
        'process_id',
        'file_path',
        'src_ip',
        'src_port',
        'dst_ip',
        'dst_port',
        'bytes_sent',
        'alert_type_id',
        'severity_id',
        'description',
        'event_hash',
    ];

    protected $appends = [
        'timestamp',
        'type',
        'process_name',
        'file',
        'alert_type',
        'severity',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'event_type_id' => 'integer',
            'pid' => 'integer',
            'process_id' => 'integer',
            'src_port' => 'integer',
            'dst_port' => 'integer',
            'bytes_sent' => 'integer',
            'alert_type_id' => 'integer',
            'severity_id' => 'integer',
        ];
    }

    public function scopeWithLookups(Builder $query): Builder
    {
        return $query->with([
            'eventType:id,name',
            'process:id,process_name',
            'alertCategory:id,name',
            'severityLevel:id,name',
        ]);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessCatalog::class, 'process_id');
    }

    public function alertCategory(): BelongsTo
    {
        return $this->belongsTo(AlertType::class, 'alert_type_id');
    }

    public function severityLevel(): BelongsTo
    {
        return $this->belongsTo(SeverityLevel::class, 'severity_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'event_time' => $this->event_time?->toIso8601String(),
            'timestamp' => $this->timestamp,
            'event_type_id' => $this->event_type_id,
            'type' => $this->type,
            'pid' => $this->pid,
            'process_id' => $this->process_id,
            'process_name' => $this->process_name,
            'file_path' => $this->file_path,
            'file' => $this->file,
            'src_ip' => $this->src_ip,
            'src_port' => $this->src_port,
            'dst_ip' => $this->dst_ip,
            'dst_port' => $this->dst_port,
            'bytes_sent' => $this->bytes_sent,
            'alert_type_id' => $this->alert_type_id,
            'alert_type' => $this->alert_type,
            'severity_id' => $this->severity_id,
            'severity' => $this->severity,
            'description' => $this->description,
            'event_hash' => $this->event_hash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function getTimestampAttribute(): ?string
    {
        return $this->event_time?->toIso8601String();
    }

    public function getTypeAttribute(): ?string
    {
        return $this->resolveRelatedValue('eventType', 'name');
    }

    public function getProcessNameAttribute(): ?string
    {
        return $this->resolveRelatedValue('process', 'process_name');
    }

    public function getFileAttribute(): ?string
    {
        return $this->file_path;
    }

    public function getAlertTypeAttribute(): ?string
    {
        return $this->resolveRelatedValue('alertCategory', 'name');
    }

    public function getSeverityAttribute(): ?string
    {
        return $this->resolveRelatedValue('severityLevel', 'name');
    }

    private function resolveRelatedValue(string $relation, string $field): ?string
    {
        if ($this->relationLoaded($relation)) {
            $related = $this->getRelation($relation);

            if (! $related instanceof Model) {
                return null;
            }

            $value = $related->getAttribute($field);

            return $value === null ? null : (string) $value;
        }

        /** @var Relation $relationQuery */
        $relationQuery = $this->{$relation}();
        $value = $relationQuery->value($field);

        return $value === null ? null : (string) $value;
    }
}
