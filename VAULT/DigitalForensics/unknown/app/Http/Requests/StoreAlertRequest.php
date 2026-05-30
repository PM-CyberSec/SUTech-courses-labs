<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_time' => ['nullable', 'date'],
            'timestamp' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:100'],
            'pid' => ['nullable', 'integer', 'min:0'],
            'process_id' => ['nullable', 'integer', 'exists:process_catalog,id'],
            'process_name' => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'string', 'max:255'],
            'src_ip' => ['nullable', 'ip'],
            'dst_ip' => ['nullable', 'ip'],
            'src_port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'dst_port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'bytes_sent' => ['nullable', 'integer', 'min:0'],
            'alert_type_id' => ['nullable', 'integer', 'exists:alert_types,id'],
            'alert_type' => ['nullable', 'string', 'max:150'],
            'severity_id' => ['nullable', 'integer', 'exists:severity_levels,id'],
            'severity' => ['nullable', 'in:LOW,MEDIUM,HIGH,CRITICAL'],
            'description' => ['nullable', 'string', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $alert = $this->input('alert');
        if (! is_array($alert)) {
            $alert = [];
        }

        $eventTime = $this->input('event_time')
            ?: ($this->input('timestamp') ?: ($this->input('time') ?: $this->input('ts')));

        $severity = $this->input('severity');
        if (is_numeric($severity)) {
            $severity = $this->severityFromNumeric((int) $severity);
        }

        if (($severity === null || $severity === '') && ! $this->filled('severity_id')) {
            $severityNum = $this->input('severity_num')
                ?? $this->input('priority')
                ?? ($alert['severity'] ?? null);

            if (is_numeric($severityNum)) {
                $severity = $this->severityFromNumeric((int) $severityNum);
            }
        }

        $this->merge([
            'type' => 'alert',
            'event_time' => $eventTime ?: now()->toIso8601String(),
            'timestamp' => $this->input('timestamp') ?: ($eventTime ?: now()->toIso8601String()),
            'severity' => $severity ?: ($this->input('severity') ?: 'LOW'),
            'process_name' => $this->input('process_name')
                ?: ($this->input('process') ?: ($this->input('executable') ?: $this->input('program'))),
            'file_path' => $this->input('file_path')
                ?: ($this->input('file') ?: $this->input('path')),
            'src_ip' => $this->input('src_ip') ?: ($this->input('source_ip') ?: data_get($this->input('src'), 'ip')),
            'dst_ip' => $this->input('dst_ip')
                ?: ($this->input('dest_ip') ?: ($this->input('destination_ip') ?: data_get($this->input('dst'), 'ip'))),
            'src_port' => $this->input('src_port') ?? ($this->input('source_port') ?? data_get($this->input('src'), 'port')),
            'dst_port' => $this->input('dst_port')
                ?? ($this->input('dest_port') ?? ($this->input('destination_port') ?? data_get($this->input('dst'), 'port'))),
            'alert_type' => $this->input('alert_type')
                ?: ($this->input('alert_signature')
                    ?: ($this->input('signature')
                        ?: (($alert['signature'] ?? null)
                            ?: ($this->input('category') ?: ($alert['category'] ?? null))))),
            'description' => $this->input('description') ?: ($this->input('message') ?: $this->input('summary')),
        ]);

        foreach (['event_time', 'timestamp', 'process_name', 'file_path', 'file', 'src_ip', 'dst_ip', 'alert_type', 'description'] as $key) {
            if ($this->isNullMarker($this->input($key))) {
                $this->merge([$key => null]);
            }
        }

        $this->merge([
            'severity' => strtoupper((string) ($this->input('severity') ?: 'LOW')),
        ]);
    }

    private function isNullMarker(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['', '-', '--', 'null', '(null)', 'n/a'], true);
    }

    private function severityFromNumeric(int $severity): string
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
}
