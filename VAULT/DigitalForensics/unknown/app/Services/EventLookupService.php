<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AlertType;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;

class EventLookupService
{
    public function resolveEventTypeId(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            $id = (int) $value;

            return EventType::query()->whereKey($id)->value('id');
        }

        $name = strtolower(trim((string) $value));
        if ($name === '') {
            return null;
        }

        $record = EventType::query()->firstOrCreate(
            ['name' => $name],
            ['created_at' => now(), 'updated_at' => now()],
        );

        return (int) $record->id;
    }

    public function findEventTypeIdByName(?string $name): ?int
    {
        $normalized = strtolower(trim((string) $name));
        if ($normalized === '') {
            return null;
        }

        $id = EventType::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function resolveProcessId(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            $id = (int) $value;

            return ProcessCatalog::query()->whereKey($id)->value('id');
        }

        $processName = trim((string) $value);
        if ($processName === '') {
            return null;
        }

        $record = ProcessCatalog::query()->firstOrCreate(
            ['process_name' => $processName],
            ['created_at' => now(), 'updated_at' => now()],
        );

        return (int) $record->id;
    }

    public function resolveAlertTypeId(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            $id = (int) $value;

            return AlertType::query()->whereKey($id)->value('id');
        }

        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        $record = AlertType::query()->firstOrCreate(
            ['name' => $name],
            ['created_at' => now(), 'updated_at' => now()],
        );

        return (int) $record->id;
    }

    public function findAlertTypeIdByName(?string $name): ?int
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return null;
        }

        $id = AlertType::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function resolveSeverityId(int|string|null $value): int
    {
        if ($value === null || $value === '') {
            return $this->lowSeverityId();
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            $id = (int) $value;
            $foundId = SeverityLevel::query()->whereKey($id)->value('id');

            return $foundId !== null ? (int) $foundId : $this->lowSeverityId();
        }

        $name = strtoupper(trim((string) $value));
        if (! in_array($name, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            $name = 'LOW';
        }

        $record = SeverityLevel::query()->firstOrCreate(
            ['name' => $name],
            ['created_at' => now(), 'updated_at' => now()],
        );

        return (int) $record->id;
    }

    public function findSeverityIdByName(?string $name): ?int
    {
        $normalized = strtoupper(trim((string) $name));
        if (! in_array($normalized, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            return null;
        }

        $id = SeverityLevel::query()->where('name', $normalized)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function highSeverityIds(): array
    {
        return SeverityLevel::query()
            ->whereIn('name', ['HIGH', 'CRITICAL'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function findProcessIdByName(?string $processName): ?int
    {
        $normalized = trim((string) $processName);
        if ($normalized === '') {
            return null;
        }

        $id = ProcessCatalog::query()
            ->whereRaw('LOWER(process_name) = ?', [strtolower($normalized)])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function lowSeverityId(): int
    {
        return (int) SeverityLevel::query()
            ->firstOrCreate(
                ['name' => 'LOW'],
                ['created_at' => now(), 'updated_at' => now()],
            )
            ->id;
    }
}
