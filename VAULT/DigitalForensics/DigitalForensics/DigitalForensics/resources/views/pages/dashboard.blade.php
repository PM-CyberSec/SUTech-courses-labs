@extends('layouts.master')

@section('title', 'Dashboard | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title">Security Overview</h2>
        <p class="page-description">Realtime SOC summary with operational metrics and quick incident lookup.</p>
    </div>
    <div class="page-meta">
        <span id="dashboard-last-updated">Waiting for first update</span>
    </div>
</section>

<section class="stats-grid" aria-label="Security metrics">
    <article class="card stat-card" data-tone="low">
        <h3>Total Events</h3>
        <p id="stat-total-events">{{ $totalEvents }}</p>
    </article>
    <article class="card stat-card" data-tone="critical">
        <h3>Critical Severity</h3>
        <p id="stat-critical-severity">{{ $criticalSeverity }}</p>
    </article>
    <article class="card stat-card" data-tone="high">
        <h3>High Severity</h3>
        <p id="stat-high-severity">{{ $highSeverity }}</p>
    </article>
    <article class="card stat-card" data-tone="medium">
        <h3>Medium Severity</h3>
        <p id="stat-medium-severity">{{ $mediumSeverity }}</p>
    </article>
    <article class="card stat-card" data-tone="low">
        <h3>Low Severity</h3>
        <p id="stat-low-severity">{{ $lowSeverity }}</p>
    </article>
    <article class="card stat-card" data-tone="critical">
        <h3>Alert Events</h3>
        <p id="stat-alert-events">{{ $alertEvents }}</p>
    </article>
    <article class="card stat-card" data-tone="medium">
        <h3>Network Events</h3>
        <p id="stat-network-events">{{ $networkEvents }}</p>
    </article>
    <article class="card stat-card" data-tone="low">
        <h3>Process Events</h3>
        <p id="stat-process-events">{{ $processEvents }}</p>
    </article>
</section>

<div class="card table-shell">
    <div class="card-header">
        <div>
            <h3 class="card-title">Quick Event Lookup</h3>
            <p class="card-subtitle">Search and inspect current event flow without leaving the dashboard.</p>
        </div>
        <p id="dashboard-events-loading" class="loading-note" role="status">Ready</p>
    </div>

    <form id="dashboard-events-form" class="table-controls" autocomplete="off">
        <div class="filters-grid">
            <div class="control control-search">
                <label for="dashboard-events-search">Search</label>
                <input id="dashboard-events-search" name="search" type="search" placeholder="ID, type, severity, process, IP, description..." value="{{ request()->query('search', '') }}">
            </div>

            <div class="control control-medium">
                <label for="dashboard-events-type">Type</label>
                <select id="dashboard-events-type" name="type">
                    <option value="" @selected(request()->query('type', '') === '')>All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}" @selected(request()->query('type') === $eventType->name)>{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="dashboard-events-severity">Severity</label>
                <select id="dashboard-events-severity" name="severity">
                    <option value="" @selected(request()->query('severity', '') === '')>All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}" @selected(request()->query('severity') === $severity->name)>{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-sort">Sort By</label>
                <select id="dashboard-events-sort" name="sort_by">
                    <option value="event_time" @selected(request()->query('sort_by', 'event_time') === 'event_time')>Time</option>
                    <option value="severity" @selected(request()->query('sort_by') === 'severity')>Severity</option>
                    <option value="id" @selected(request()->query('sort_by') === 'id')>ID</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-dir">Direction</label>
                <select id="dashboard-events-dir" name="sort_dir">
                    <option value="desc" @selected(request()->query('sort_dir', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request()->query('sort_dir') === 'asc')>Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-per-page">Per Page</label>
                <select id="dashboard-events-per-page" name="per_page">
                    <option value="10" @selected((string) request()->query('per_page', '25') === '10')>10</option>
                    <option value="25" @selected((string) request()->query('per_page', '25') === '25')>25</option>
                    <option value="50" @selected((string) request()->query('per_page', '25') === '50')>50</option>
                </select>
            </div>
        </div>

        <div class="controls-actions">
            <button type="submit" class="button-primary">Apply Filters</button>
            <button type="button" class="button-secondary" id="dashboard-events-reset">Reset</button>
            <a class="chip-link" href="{{ route('events.index') }}">Open Full Events Explorer</a>
            <span id="dashboard-events-active-filters" class="active-filter-chips">
                <span class="no-active-filters">No active filters</span>
            </span>
        </div>
    </form>

    <div class="table-scroll">
        <table aria-describedby="dashboard-events-description">
            <caption id="dashboard-events-description" class="visually-hidden">Quick dashboard event table</caption>
            <thead>
                <tr>
                    <th scope="col" data-sort="id" class="sortable">ID</th>
                    <th scope="col" data-sort="event_time" class="sortable">Time</th>
                    <th scope="col" data-sort="type" class="sortable">Type</th>
                    <th scope="col" data-sort="severity" class="sortable">Severity</th>
                    <th scope="col">Process</th>
                    <th scope="col">Network</th>
                    <th scope="col">Alert Type</th>
                    <th scope="col">Description</th>
                    <th scope="col">AI Label</th>
                    <th scope="col">AI Confidence</th>
                </tr>
            </thead>
            <tbody id="dashboard-events-body">
            @forelse($dashboardEvents as $event)
                <tr data-event-id="{{ $event->id }}">
                    <td class="table-id" data-label="ID"><span class="cell-value"><a href="{{ route('events.show', $event->id) }}" style="color: var(--brand);">{{ $event->id }}</a></span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">{{ $event->event_time?->format('Y-m-d H:i:s') ?? '-' }}</span></td>
                    <td data-label="Type"><span class="cell-value"><span class="type-pill" data-type="{{ strtolower($event->type ?? 'event') }}">{{ strtoupper($event->type ?? 'event') }}</span></span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="{{ 'severity-' . strtolower($event->severity ?? 'low') }}">{{ $event->severity ?? 'LOW' }}</span></span></td>
                    <td data-label="Process"><span class="cell-value"><span class="mono">{{ $event->pid }}</span> {{ $event->process_name ?? '-' }}</span></td>
                    <td class="mono" data-label="Source / Destination"><span class="cell-value">{{ $event->src_ip ?? '-' }}:{{ $event->src_port ?? 0 }} → {{ $event->dst_ip ?? '-' }}:{{ $event->dst_port ?? 0 }}</span></td>
                    <td data-label="Alert Type"><span class="cell-value">{{ $event->alert_type ?? '-' }}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="{{ $event->description ?? '-' }}">{{ \Illuminate\Support\Str::limit($event->description ?? '-', 110) }}</span></td>
                    <td data-label="AI Label"><span class="cell-value"><span class="type-pill" data-type="{{ $event->ai_label ?? 'benign' }}">{{ strtoupper($event->ai_label ?? 'benign') }}</span></span></td>
                    <td data-label="AI Confidence"><span class="cell-value">{{ number_format($event->confidence ?? 0, 2) }}</span></td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="10">No events available</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="dashboard-events-count" class="pagination-info">Showing 0 - 0 of 0 events</p>
        <div class="pagination-group">
            <button type="button" id="dashboard-events-prev">Prev</button>
            <span
                id="dashboard-events-page"
                class="pagination-info"
                data-current-page="{{ $dashboardEvents->currentPage() }}"
                data-last-page="{{ max(1, $dashboardEvents->lastPage()) }}"
            >Page {{ $dashboardEvents->currentPage() }} / {{ max(1, $dashboardEvents->lastPage()) }}</span>
            <button type="button" id="dashboard-events-next">Next</button>
        </div>
    </div>
</div>

<script type="module" nonce="{{ $cspNonce ?? '' }}">
(() => {
    const fields = {
        total_events: document.getElementById('stat-total-events'),
        critical_severity: document.getElementById('stat-critical-severity'),
        high_severity: document.getElementById('stat-high-severity'),
        medium_severity: document.getElementById('stat-medium-severity'),
        low_severity: document.getElementById('stat-low-severity'),
        alert_events: document.getElementById('stat-alert-events'),
        network_events: document.getElementById('stat-network-events'),
        process_events: document.getElementById('stat-process-events'),
    };

    const lastUpdated = document.getElementById('dashboard-last-updated');
    const seenRealtimeIds = new Set();

    const readInt = (element) => {
        if (!element) {
            return 0;
        }

        const normalized = String(element.textContent ?? '0').replaceAll(',', '').trim();
        const parsed = Number.parseInt(normalized, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const stampUpdatedAt = (prefix = 'Last update') => {
        if (!lastUpdated) {
            return;
        }

        lastUpdated.textContent = `${prefix}: ${new Date().toLocaleTimeString()}`;
    };

    const applyStats = (stats) => {
        Object.entries(fields).forEach(([key, el]) => {
            if (el) {
                el.textContent = Number(stats[key] ?? 0).toLocaleString();
            }
        });

        stampUpdatedAt('Synced');
    };

    const currentStats = () => {
        return Object.fromEntries(
            Object.entries(fields).map(([key, el]) => [key, readInt(el)]),
        );
    };

    const applyRealtimeIncrement = (eventPayload) => {
        const eventId = Number(eventPayload?.id ?? 0);
        if (eventId <= 0 || seenRealtimeIds.has(eventId)) {
            return;
        }

        seenRealtimeIds.add(eventId);
        if (seenRealtimeIds.size > 2000) {
            seenRealtimeIds.clear();
            seenRealtimeIds.add(eventId);
        }

        const stats = currentStats();
        stats.total_events += 1;

        const severity = String(eventPayload?.severity ?? 'LOW').toUpperCase();
        if (severity === 'CRITICAL') {
            stats.critical_severity += 1;
        } else if (severity === 'HIGH') {
            stats.high_severity += 1;
        } else if (severity === 'MEDIUM') {
            stats.medium_severity += 1;
        } else {
            stats.low_severity += 1;
        }

        const type = String(eventPayload?.type ?? '').toLowerCase();
        if (type === 'alert') {
            stats.alert_events += 1;
        } else if (type === 'network') {
            stats.network_events += 1;
        } else if (type === 'process') {
            stats.process_events += 1;
        }

        Object.entries(fields).forEach(([key, el]) => {
            if (el) {
                el.textContent = Number(stats[key] ?? 0).toLocaleString();
            }
        });

        stampUpdatedAt('Realtime');
    };

    const fetchStats = async () => {
        try {
            const stats = await window.dldsFetchJson('/api/dlds/stats', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }, 8000);
            applyStats(stats);
            window.dldsMarkDataSync('dashboard-stats');
        } catch (error) {
            stampUpdatedAt('Sync retrying');
            window.dldsMarkDataSyncFailure('dashboard-stats');
        }
    };
    
    // Initial fetch
    fetchStats();

    window.addEventListener('dlds:event-created', (domEvent) => {
        const eventPayload = window.dldsExtractRealtimeEvent(domEvent.detail);
        if (!eventPayload) {
            return;
        }

        applyRealtimeIncrement(eventPayload);
    });
})();

(() => {
const endpoint = '/api/dlds/public/events';
    const form = document.getElementById('dashboard-events-form');
    const body = document.getElementById('dashboard-events-body');
    const loading = document.getElementById('dashboard-events-loading');
    const count = document.getElementById('dashboard-events-count');
    const pageIndicator = document.getElementById('dashboard-events-page');
    const prevPageBtn = document.getElementById('dashboard-events-prev');
    const nextPageBtn = document.getElementById('dashboard-events-next');
    const resetBtn = document.getElementById('dashboard-events-reset');
    const activeFilters = document.getElementById('dashboard-events-active-filters');
    const sortableHeaders = Array.from(form.closest('.table-shell')?.querySelectorAll('th.sortable') ?? []);

    if (!form || !body) {
        return;
    }

    const defaults = {
        search: '',
        type: '',
        severity: '',
        sort_by: 'event_time',
        sort_dir: 'desc',
        per_page: '25',
        page: '1',
    };

    const state = { ...defaults };
    let realtimeTimer = null;

    const getInitialPagination = () => {
        const currentPage = Number(pageIndicator?.dataset.currentPage ?? 1);
        const lastPage = Number(pageIndicator?.dataset.lastPage ?? 1);

        return {
            page: Number.isFinite(currentPage) && currentPage > 0 ? currentPage : 1,
            lastPage: Number.isFinite(lastPage) && lastPage > 0 ? lastPage : 1,
        };
    };

    const parseUrlState = () => {
        const params = new URLSearchParams(window.location.search);
        Object.keys(defaults).forEach((key) => {
            const value = params.get(key);
            if (value !== null && value !== '') {
                state[key] = value;
            }
        });
    };

    const syncControls = () => {
        Object.keys(defaults).forEach((key) => {
            const el = form.elements.namedItem(key);
            if (el) {
                el.value = state[key] ?? defaults[key];
            }
        });
    };

    const setLoading = (message) => {
        if (loading) {
            loading.textContent = message;
        }
    };

    const typePill = (type) => {
        const normalized = String(type ?? 'event').toLowerCase();
        return `<span class="type-pill" data-type="${window.dldsEscapeHtml(normalized)}">${window.dldsEscapeHtml(normalized.toUpperCase())}</span>`;
    };

    const renderRows = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr class="empty-row"><td colspan="10">No events match the current dashboard filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((event) => {
            const severity = event.severity ?? 'LOW';
            return `
                <tr>
                    <td class="table-id" data-label="ID"><span class="cell-value"><a href="/events/${Number(event.id)}" style="color: var(--brand);">${Number(event.id)}</a></span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">${window.dldsEscapeHtml(window.dldsFormatTime(event.event_time))}</span></td>
                    <td data-label="Type"><span class="cell-value">${typePill(event.type)}</span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></span></td>
                    <td data-label="Process"><span class="cell-value"><span class="mono">${Number(event.pid ?? 0)}</span> ${window.dldsEscapeHtml(event.process_name ?? '-')}</span></td>
                    <td class="mono" data-label="Source / Destination"><span class="cell-value">${window.dldsEscapeHtml(event.src_ip ?? '-')}:${Number(event.src_port ?? 0)} → ${window.dldsEscapeHtml(event.dst_ip ?? '-')}:${Number(event.dst_port ?? 0)}</span></td>
                    <td data-label="Alert Type"><span class="cell-value">${window.dldsEscapeHtml(event.alert_type ?? '-')}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="${window.dldsEscapeHtml(event.description ?? '-')}">${window.dldsEscapeHtml(window.dldsClipText(event.description ?? '-', 110))}</span></td>
                    <td data-label="AI Label"><span class="cell-value"><span class="type-pill" data-type="${window.dldsEscapeHtml(event.ai_label ?? 'benign')}">${window.dldsEscapeHtml((event.ai_label ?? 'benign').toUpperCase())}</span></span></td>
                    <td data-label="AI Confidence"><span class="cell-value">${Number(event.confidence ?? 0).toFixed(2)}</span></td>
                </tr>
            `;
        }).join('');
    };

    const applyMeta = (meta) => {
        const total = Number(meta?.total ?? 0);
        const page = Number(meta?.current_page ?? meta?.page ?? 1);
        const lastPage = Number(meta?.last_page ?? 1);
        const from = Number(meta?.from ?? 0);
        const to = Number(meta?.to ?? 0);

        state.page = String(page);
        state._lastPage = lastPage;
        count.textContent = `Showing ${from} - ${to} of ${total} events`;
        pageIndicator.textContent = `Page ${page} / ${Math.max(lastPage, 1)}`;
        pageIndicator.dataset.currentPage = String(page);
        pageIndicator.dataset.lastPage = String(Math.max(lastPage, 1));
        prevPageBtn.disabled = page <= 1;
        nextPageBtn.disabled = page >= lastPage;
    };

    const buildQuery = () => {
        return window.dldsCleanQuery(state, defaults);
    };

    const writeUrl = () => {
        const query = buildQuery();
        const url = new URL(window.location.href);
        url.search = query.toString();
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    };

    const syncStateFromControls = () => {
        Object.keys(defaults).forEach((key) => {
            if (key === 'page') return;
            const el = form.elements.namedItem(key);
            if (el) {
                state[key] = el.value;
            }
        });
    };

    const filterLabels = {
        search: 'Search',
        type: 'Type',
        severity: 'Severity',
        sort_by: 'Sort',
        sort_dir: 'Direction',
        per_page: 'Per page',
    };

    const renderActiveFilters = () => {
        window.dldsRenderFilterChips({
            container: activeFilters,
            state,
            defaults,
            labels: filterLabels,
        });
    };

    const updateHeaderArrows = () => {
        sortableHeaders.forEach((th) => {
            if (th.dataset.sort === state.sort_by) {
                th.dataset.dir = state.sort_dir;
            } else {
                delete th.dataset.dir;
            }
        });
    };

    sortableHeaders.forEach((th) => {
        th.addEventListener('click', () => {
            const sortKey = th.dataset.sort;
            if (state.sort_by === sortKey) {
                state.sort_dir = state.sort_dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort_by = sortKey;
                state.sort_dir = 'desc';
            }
            if (form.elements.namedItem('sort_by')) form.elements.namedItem('sort_by').value = state.sort_by;
            if (form.elements.namedItem('sort_dir')) form.elements.namedItem('sort_dir').value = state.sort_dir;
            state.page = '1';
            updateHeaderArrows();
            renderActiveFilters();
            fetchData({ reason: 'Sort updated' });
        });
    });

    const fetchData = async ({ reason = 'Synced', pushUrl = true } = {}) => {
        updateHeaderArrows();
        setLoading('Loading events...');

        try {
            const payload = await window.dldsFetchJson(window.dldsEndpointWithQuery(endpoint, buildQuery()), {
                headers: { 
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
            }, 8000);
            renderRows(payload.data ?? []);
            applyMeta(payload.meta ?? {});
            setLoading(`Quick view ${reason.toLowerCase()}`);
            renderActiveFilters();
            window.dldsMarkDataSync('dashboard-events');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Unable to refresh; showing last known events');
            window.dldsMarkDataSyncFailure('dashboard-events');
            console.error('Failed to fetch dashboard event table:', error);
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        syncStateFromControls();
        state.page = '1';
        fetchData({ reason: 'Filters applied' });
    });

    prevPageBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const current = Number(state.page || '1');
        if (current <= 1) return;
        state.page = String(current - 1);
        fetchData({ reason: 'Page updated' });
    });

    nextPageBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const current = Number(state.page || '1');
        const lastPage = Number(state._lastPage ?? 1);
        if (current >= lastPage) return;
        state.page = String(current + 1);
        fetchData({ reason: 'Page updated' });
    });

    resetBtn.addEventListener('click', () => {
        Object.assign(state, defaults);
        Object.keys(defaults).forEach((key) => {
            const el = form.elements.namedItem(key);
            if (el && key !== 'page') {
                el.value = defaults[key];
            }
        });
        fetchData({ reason: 'Filters reset' });
    });

    window.addEventListener('dlds:event-created', () => {
        window.clearTimeout(realtimeTimer);
        realtimeTimer = window.setTimeout(() => {
            fetchData({ reason: 'Realtime sync' });
        }, 500);
    });

    window.addEventListener('dlds:auto-refresh', () => {
        fetchData({ reason: 'Auto-refresh sync' });
        fetchStats();
    });

    parseUrlState();
    const initialPagination = getInitialPagination();
    state.page = String(initialPagination.page);
    state._lastPage = initialPagination.lastPage;
    syncControls();
    renderActiveFilters();
    fetchData({ reason: 'Initial sync', pushUrl: false });
})();
</script>
@endsection
