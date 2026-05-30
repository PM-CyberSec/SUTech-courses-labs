<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DldsEventQueryService
{
    public function resolvePerPage(Request $request, int $default = 25, int $max = 200): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return min(max($perPage, 1), $max);
    }

    public function paginationMeta(LengthAwarePaginator $results): array
    {
        return [
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'from' => $results->firstItem(),
            'to' => $results->lastItem(),
        ];
    }

    public function applyCommonFilters(Builder $query, Request $request, EventLookupService $lookupService): void
    {
        if ($request->filled('event_type_id')) {
            $query->where('event_type_id', (int) $request->query('event_type_id'));
        }

        if ($request->filled('severity_id')) {
            $query->where('severity_id', (int) $request->query('severity_id'));
        }

        if ($request->filled('alert_type_id')) {
            $query->where('alert_type_id', (int) $request->query('alert_type_id'));
        }

        if ($request->filled('process_id')) {
            $query->where('process_id', (int) $request->query('process_id'));
        }

        if ($request->filled('type')) {
            $eventTypeId = $lookupService->findEventTypeIdByName((string) $request->query('type'));
            if ($eventTypeId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('event_type_id', $eventTypeId);
        }

        if ($request->filled('severity')) {
            $severityId = $lookupService->findSeverityIdByName((string) $request->query('severity'));
            if ($severityId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('severity_id', $severityId);
        }

        if ($request->filled('alert_type')) {
            $alertTypeId = $lookupService->findAlertTypeIdByName((string) $request->query('alert_type'));
            if ($alertTypeId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('alert_type_id', $alertTypeId);
        }

        if ($request->filled('process_name')) {
            $needle = trim((string) $request->query('process_name'));
            if ($needle !== '') {
                $query->whereHas('process', static fn ($inner) => $inner
                    ->where('process_name', 'like', '%'.$needle.'%'));
            }
        }

        if ($request->filled('pid')) {
            $pid = (int) $request->query('pid');
            if ($pid >= 0) {
                $query->where('pid', $pid);
            }
        }

        if ($request->filled('src_ip')) {
            $query->where('src_ip', 'like', '%'.trim((string) $request->query('src_ip')).'%');
        }

        if ($request->filled('dst_ip')) {
            $query->where('dst_ip', 'like', '%'.trim((string) $request->query('dst_ip')).'%');
        }

        if ($request->filled('port')) {
            $port = (int) $request->query('port');
            if ($port >= 0) {
                $query->where(static fn ($inner) => $inner
                    ->where('src_port', $port)
                    ->orWhere('dst_port', $port));
            }
        }

        if ($request->filled('has_file')) {
            $hasFile = $this->asNullableBool($request->query('has_file'));
            if ($hasFile === true) {
                $query->whereNotNull('file_path')->where('file_path', '!=', '');
            } elseif ($hasFile === false) {
                $query->where(static fn ($inner) => $inner
                    ->whereNull('file_path')
                    ->orWhere('file_path', ''));
            }
        }

        if ($request->filled('date_from')) {
            $from = $this->parseDate($request->query('date_from'));
            if ($from !== null) {
                $query->where('event_time', '>=', $from->startOfDay()->toDateTimeString());
            }
        }

        if ($request->filled('date_to')) {
            $to = $this->parseDate($request->query('date_to'));
            if ($to !== null) {
                $query->where('event_time', '<=', $to->endOfDay()->toDateTimeString());
            }
        }

        $scope = strtolower(trim((string) $request->query('scope', '')));
        if ($scope === 'alert') {
            $this->applyAlertScope($query, $lookupService);
        } elseif ($scope === 'network') {
            $this->applyNetworkScope($query, $lookupService);
        } elseif ($scope === 'process') {
            $this->applyProcessScope($query, $lookupService);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }
    }

    /**
     * @param  string[]  $allowedSortKeys
     */
    public function applySort(
        Builder $query,
        Request $request,
        array $allowedSortKeys = [],
        string $defaultSortBy = 'event_time',
        string $defaultSortDir = 'desc',
    ): void {
        $allowed = $allowedSortKeys === []
            ? [
                'id',
                'event_time',
                'time',
                'severity',
                'type',
                'process_name',
                'alert_type',
                'src_ip',
                'dst_ip',
                'src_port',
                'dst_port',
                'bytes_sent',
                'pid',
                'file_path',
                'created_at',
            ]
            : $allowedSortKeys;

        $sortBy = strtolower(trim((string) $request->query('sort_by', $defaultSortBy)));
        if ($sortBy === 'time') {
            $sortBy = 'event_time';
        }

        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = $defaultSortBy;
        }

        $sortDir = strtolower((string) $request->query('sort_dir', $defaultSortDir)) === 'asc'
            ? 'asc'
            : 'desc';

        switch ($sortBy) {
            case 'id':
            case 'event_time':
            case 'src_ip':
            case 'dst_ip':
            case 'src_port':
            case 'dst_port':
            case 'bytes_sent':
            case 'pid':
            case 'file_path':
            case 'created_at':
                $query->orderBy($sortBy, $sortDir);
                break;

            case 'severity':
                $query->orderByRaw(
                    "CASE UPPER(COALESCE((SELECT name FROM severity_levels WHERE severity_levels.id = dlds_events.severity_id), 'LOW'))
                        WHEN 'CRITICAL' THEN 4
                        WHEN 'HIGH' THEN 3
                        WHEN 'MEDIUM' THEN 2
                        WHEN 'LOW' THEN 1
                        ELSE 0
                    END {$sortDir}",
                );
                break;

            case 'type':
                $query->orderByRaw(
                    "(SELECT name FROM event_types WHERE event_types.id = dlds_events.event_type_id) {$sortDir}",
                );
                break;

            case 'process_name':
                $query->orderByRaw(
                    "(SELECT process_name FROM process_catalog WHERE process_catalog.id = dlds_events.process_id) {$sortDir}",
                );
                break;

            case 'alert_type':
                $query->orderByRaw(
                    "(SELECT name FROM alert_types WHERE alert_types.id = dlds_events.alert_type_id) {$sortDir}",
                );
                break;

            default:
                $query->orderBy($defaultSortBy, $defaultSortDir);
        }

        if ($sortBy !== 'id') {
            $query->orderByDesc('id');
        }
    }

    private function applySearch(Builder $query, string $needle): void
    {
        $numericNeedle = ctype_digit($needle) ? (int) $needle : null;
        $pattern = '%'.$needle.'%';

        $query->where(static function ($inner) use ($pattern, $numericNeedle): void {
            $inner->where('description', 'like', $pattern)
                ->orWhere('file_path', 'like', $pattern)
                ->orWhere('src_ip', 'like', $pattern)
                ->orWhere('dst_ip', 'like', $pattern)
                ->orWhere('event_hash', 'like', $pattern)
                ->orWhere('event_time', 'like', $pattern)
                ->orWhereHas('eventType', static fn ($relation) => $relation
                    ->where('name', 'like', $pattern))
                ->orWhereHas('process', static fn ($relation) => $relation
                    ->where('process_name', 'like', $pattern))
                ->orWhereHas('alertCategory', static fn ($relation) => $relation
                    ->where('name', 'like', $pattern))
                ->orWhereHas('severityLevel', static fn ($relation) => $relation
                    ->where('name', 'like', $pattern));

            if ($numericNeedle !== null) {
                $inner->orWhere('id', $numericNeedle)
                    ->orWhere('pid', $numericNeedle)
                    ->orWhere('src_port', $numericNeedle)
                    ->orWhere('dst_port', $numericNeedle)
                    ->orWhere('bytes_sent', $numericNeedle);
            }
        });
    }

    public function applyAlertScope(Builder $query, EventLookupService $lookupService): void
    {
        $alertTypeId = $lookupService->findEventTypeIdByName('alert');
        $highSeverityIds = $lookupService->highSeverityIds();

        $query->where(function ($inner) use ($alertTypeId, $highSeverityIds): void {
            if ($alertTypeId !== null) {
                $inner->where('event_type_id', $alertTypeId)
                    ->orWhereNotNull('alert_type_id')
                    ->orWhere('description', 'like', '%alert%');

                if ($highSeverityIds !== []) {
                    $inner->orWhereIn('severity_id', $highSeverityIds);
                }

                return;
            }

            $inner->whereNotNull('alert_type_id')
                ->orWhere('description', 'like', '%alert%');

            if ($highSeverityIds !== []) {
                $inner->orWhereIn('severity_id', $highSeverityIds);
            }
        });
    }

    public function applyNetworkScope(Builder $query, EventLookupService $lookupService): void
    {
        $networkTypeId = $lookupService->findEventTypeIdByName('network');

        $query->where(function ($inner) use ($networkTypeId): void {
            if ($networkTypeId !== null) {
                $inner->where('event_type_id', $networkTypeId)
                    ->orWhere(function ($networkFallback): void {
                        $networkFallback->whereNull('event_type_id')
                            ->where(function ($networkFields): void {
                                $networkFields->whereNotNull('src_ip')
                                    ->orWhereNotNull('dst_ip');
                            });
                    });

                return;
            }

            $inner->whereNotNull('src_ip')
                ->orWhereNotNull('dst_ip');
        });
    }

    public function applyProcessScope(Builder $query, EventLookupService $lookupService): void
    {
        $processTypeId = $lookupService->findEventTypeIdByName('process');

        $query->where(function ($inner) use ($processTypeId): void {
            if ($processTypeId !== null) {
                $inner->where('event_type_id', $processTypeId)
                    ->orWhere(function ($processFallback): void {
                        $processFallback->whereNull('event_type_id')
                            ->where(function ($processFields): void {
                                $processFields->where('pid', '>', 0)
                                    ->orWhereNotNull('process_id')
                                    ->orWhere(function ($filePath): void {
                                        $filePath->whereNotNull('file_path')
                                            ->where('file_path', '!=', '');
                                    });
                            });
                    });

                return;
            }

            $inner->where('pid', '>', 0)
                ->orWhereNotNull('process_id')
                ->orWhere(function ($filePath): void {
                    $filePath->whereNotNull('file_path')
                        ->where('file_path', '!=', '');
                });
        });
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function asNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }
}
