<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\NewAlertEvent;
use App\Models\DldsEvent;
use Carbon\CarbonImmutable;

class EventIngestionService
{
    public function __construct(private readonly EventLookupService $lookupService) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: DldsEvent, 1: bool}
     */
    public function ingest(array $payload): array
    {
        $eventTime = $this->normalizeDateTime(
            $this->firstPresent($payload, ['event_time', 'timestamp', 'time', 'ts']),
        );

        $detectedType = $this->detectType($payload);

        $eventTypeId = $this->lookupService->resolveEventTypeId(
            $payload['event_type_id'] ?? null,
        );
        if ($eventTypeId === null) {
            $eventTypeId = $this->lookupService->resolveEventTypeId($detectedType);
        }

        $processName = $this->extractProcessName($payload);
        $processId = $this->lookupService->resolveProcessId(
            $payload['process_id'] ?? $processName,
        );

        $alertType = $this->extractAlertType($payload);
        $alertTypeId = $this->lookupService->resolveAlertTypeId(
            $payload['alert_type_id'] ?? $alertType,
        );

        $severityInput = $this->extractSeverityInput($payload);
        $severityId = $this->lookupService->resolveSeverityId(
            $payload['severity_id'] ?? $severityInput ?? 'LOW',
        );

        $srcIp = $this->nullableString($this->firstPresent($payload, [
            'src_ip',
            'source_ip',
            'src.ip',
            'source.ip',
        ]));
        $dstIp = $this->nullableString($this->firstPresent($payload, [
            'dst_ip',
            'dest_ip',
            'destination_ip',
            'dst.ip',
            'dest.ip',
            'destination.ip',
        ]));

        $normalized = [
            'event_time' => $eventTime->toDateTimeString(),
            'event_type_id' => $eventTypeId,
            'pid' => $this->asPositiveInt($this->firstPresent($payload, ['pid', 'process.pid']) ?? 0),
            'process_id' => $processId,
            'file_path' => $this->nullableString($this->firstPresent($payload, ['file_path', 'file', 'path'])),
            'src_ip' => $srcIp,
            'src_port' => $this->asPort($this->firstPresent($payload, ['src_port', 'source_port', 'src.port']) ?? 0),
            'dst_ip' => $dstIp,
            'dst_port' => $this->asPort($this->firstPresent($payload, ['dst_port', 'dest_port', 'destination_port', 'dst.port']) ?? 0),
            'bytes_sent' => $this->asPositiveInt($this->firstPresent($payload, ['bytes_sent', 'bytes', 'flow.bytes_toserver', 'flow.bytes']) ?? 0),
            'alert_type_id' => $alertTypeId,
            'severity_id' => $severityId,
            'description' => $this->extractDescription($payload),
        ];

        $hash = $this->hashEvent($normalized);

        $event = DldsEvent::query()->firstOrCreate(
            ['event_hash' => $hash],
            [...$normalized, 'event_hash' => $hash],
        );

        $event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        if ($event->wasRecentlyCreated) {
            event(new NewAlertEvent($event));
        }

        return [$event, ! $event->wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function hashEvent(array $event): string
    {
        return hash('sha256', implode('|', [
            (string) ($event['event_time'] ?? ''),
            (string) ($event['event_type_id'] ?? ''),
            (string) ($event['pid'] ?? ''),
            (string) ($event['process_id'] ?? ''),
            (string) ($event['file_path'] ?? ''),
            (string) ($event['src_ip'] ?? ''),
            (string) ($event['src_port'] ?? ''),
            (string) ($event['dst_ip'] ?? ''),
            (string) ($event['dst_port'] ?? ''),
            (string) ($event['bytes_sent'] ?? ''),
            (string) ($event['alert_type_id'] ?? ''),
            (string) ($event['severity_id'] ?? ''),
            (string) ($event['description'] ?? ''),
        ]));
    }

    private function normalizeDateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        try {
            if (is_string($value) && trim($value) !== '') {
                return CarbonImmutable::parse($value)->utc();
            }
        } catch (\Throwable) {
            // Invalid date input falls back to now.
        }

        return CarbonImmutable::now('UTC');
    }

    private function asPositiveInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function asPort(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $port = (int) $value;

        return min(max($port, 0), 65535);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function detectType(array $payload): string
    {
        $candidate = $this->firstPresent($payload, ['type', 'event_type', 'event.kind']);
        $normalized = $this->normalizeTypeName($this->nullableString($candidate));
        if ($normalized !== null) {
            return $normalized;
        }

        $alertType = $this->extractAlertType($payload);
        $severity = $this->extractSeverityInput($payload);
        $pid = $this->asPositiveInt($this->firstPresent($payload, ['pid', 'process.pid']) ?? 0);
        $processName = $this->extractProcessName($payload);
        $filePath = $this->nullableString($this->firstPresent($payload, ['file_path', 'file', 'path']));
        $srcIp = $this->nullableString($this->firstPresent($payload, ['src_ip', 'source_ip', 'src.ip']));
        $dstIp = $this->nullableString($this->firstPresent($payload, ['dst_ip', 'dest_ip', 'destination_ip', 'dst.ip']));
        $srcPort = $this->asPort($this->firstPresent($payload, ['src_port', 'source_port', 'src.port']) ?? 0);
        $dstPort = $this->asPort($this->firstPresent($payload, ['dst_port', 'dest_port', 'destination_port', 'dst.port']) ?? 0);

        $hasAlertSignals = $alertType !== null
            || in_array((string) $severity, ['HIGH', 'CRITICAL'], true);
        if ($hasAlertSignals) {
            return 'alert';
        }

        $hasProcessSignals = $pid > 0
            || $processName !== null
            || $filePath !== null;

        $hasNetworkSignals = $srcIp !== null
            || $dstIp !== null
            || $srcPort > 0
            || $dstPort > 0;

        if ($hasProcessSignals && ! $hasNetworkSignals) {
            return 'process';
        }

        if ($hasNetworkSignals) {
            return 'network';
        }

        if ($hasProcessSignals) {
            return 'process';
        }

        return 'alert';
    }

    private function normalizeTypeName(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $normalized = strtolower(trim($type));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['alert', 'ids', 'incident', 'threat', 'suricata_alert'], true)) {
            return 'alert';
        }

        if (in_array($normalized, ['network', 'net', 'conn', 'connection', 'dns', 'http', 'tls', 'flow'], true)) {
            return 'network';
        }

        if (in_array($normalized, ['process', 'proc', 'auditd', 'file', 'syscall', 'exec', 'command'], true)) {
            return 'process';
        }

        if (in_array($normalized, ['test', 'heartbeat', 'healthcheck'], true)) {
            return 'test';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProcessName(array $payload): ?string
    {
        return $this->nullableString($this->firstPresent($payload, [
            'process_name',
            'process',
            'process_name_raw',
            'executable',
            'program',
            'process.name',
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAlertType(array $payload): ?string
    {
        return $this->nullableString($this->firstPresent($payload, [
            'alert_type',
            'category',
            'alert_signature',
            'signature',
            'alert.signature',
            'alert.category',
            'alert.name',
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSeverityInput(array $payload): string|int|null
    {
        $explicit = $this->firstPresent($payload, ['severity', 'alert.severity_label']);
        if ($explicit !== null) {
            $asString = strtoupper(trim((string) $explicit));
            if (in_array($asString, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
                return $asString;
            }
        }

        $numeric = $this->firstPresent($payload, ['severity_num', 'alert.severity', 'priority']);
        if ($numeric !== null && is_numeric($numeric)) {
            return $this->normalizeSuricataSeverity((int) $numeric);
        }

        return null;
    }

    private function normalizeSuricataSeverity(int $severity): string
    {
        if ($severity <= 1) {
            return 'CRITICAL';
        }

        if ($severity === 2) {
            return 'HIGH';
        }

        if ($severity === 3) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractDescription(array $payload): ?string
    {
        $description = $this->nullableString($this->firstPresent($payload, [
            'description',
            'message',
            'summary',
        ]));
        if ($description !== null) {
            return $description;
        }

        $signature = $this->nullableString($this->firstPresent($payload, ['alert.signature', 'alert_signature', 'signature']));
        $category = $this->nullableString($this->firstPresent($payload, ['alert.category', 'category']));

        if ($signature !== null && $category !== null) {
            return $signature.' ('.$category.')';
        }

        return $signature ?? $category;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string[]  $keys
     */
    private function firstPresent(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->payloadGet($payload, $key);
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadGet(array $payload, string $path): mixed
    {
        if (! str_contains($path, '.')) {
            return $payload[$path] ?? null;
        }

        $segments = explode('.', $path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
