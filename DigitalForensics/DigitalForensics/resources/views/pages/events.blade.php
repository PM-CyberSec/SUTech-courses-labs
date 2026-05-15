@extends('layouts.master')

@section('title', 'Events | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title">Events Stream</h2>
        <p class="page-description">Complete telemetry explorer with search, filters, sorting, and realtime updates.</p>
    </div>
    <div class="page-meta">
        <span id="events-last-updated">Loaded from server</span>
    </div>
</section>

<div class="card table-shell">
    <div class="card-header">
        <div>
            <h3 class="card-title">Event Explorer</h3>
            <p class="card-subtitle">Use filters to isolate incidents and monitor activity patterns quickly.</p>
        </div>
        <p id="events-loading" class="loading-note" role="status">Ready</p>
    </div>

    <form id="events-filter-form" class="table-controls" autocomplete="off">
        <div class="filters-grid">
            <div class="control control-search">
                <label for="events-search">Search</label>
                <input id="events-search" name="search" type="search" placeholder="ID, type, severity, process, IP, hash, file..." value="{{ request()->query('search', '') }}">
            </div>

            <div class="control control-medium">
                <label for="events-type">Type</label>
                <select id="events-type" name="type">
                    <option value="" @selected(request()->query('type', '') === '')>All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}" @selected(request()->query('type') === $eventType->name)>{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="events-severity">Severity</label>
                <select id="events-severity" name="severity">
                    <option value="" @selected(request()->query('severity', '') === '')>All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}" @selected(request()->query('severity') === $severity->name)>{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium" data-filter="alert-type">
                <label for="events-alert-type">Alert Type</label>
                <select id="events-alert-type" name="alert_type">
                    <option value="" @selected(request()->query('alert_type', '') === '')>All</option>
                    @foreach($alertTypes as $alertType)
                        <option value="{{ $alertType->name }}" @selected(request()->query('alert_type') === $alertType->name)>{{ $alertType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium" data-filter="has-file">
                <label for="events-has-file">File Presence</label>
                <select id="events-has-file" name="has_file">
                    <option value="">All</option>
                    <option value="1">With file</option>
                    <option value="0">Without file</option>
                </select>
            </div>

            <div class="control control-medium" data-filter="process">
                <label for="events-process">Process</label>
                <input id="events-process" name="process_name" type="text" list="events-process-list" placeholder="python.exe" value="{{ request()->query('process_name', '') }}">
                <datalist id="events-process-list">
                    @foreach($processCatalog as $process)
                        <option value="{{ $process->process_name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="control control-medium">
                <label for="events-src-ip">Source IP</label>
                <input id="events-src-ip" name="src_ip" type="text" placeholder="10.0.0.5" value="{{ request()->query('src_ip', '') }}">
            </div>

            <div class="control control-medium">
                <label for="events-dst-ip">Destination IP</label>
                <input id="events-dst-ip" name="dst_ip" type="text" placeholder="8.8.8.8" value="{{ request()->query('dst_ip', '') }}">
            </div>

            <div class="control control-small">
                <label for="events-date-from">From</label>
                <input id="events-date-from" name="date_from" type="date" value="{{ request()->query('date_from', '') }}">
            </div>

            <div class="control control-small">
                <label for="events-date-to">To</label>
                <input id="events-date-to" name="date_to" type="date" value="{{ request()->query('date_to', '') }}">
            </div>

            <div class="control control-small">
                <label for="events-sort-by">Sort By</label>
                <select id="events-sort-by" name="sort_by">
                    <option value="event_time" @selected(request()->query('sort_by', 'event_time') === 'event_time')>Time</option>
                    <option value="id" @selected(request()->query('sort_by') === 'id')>ID</option>
                    <option value="severity" @selected(request()->query('sort_by') === 'severity')>Severity</option>
                    <option value="type" @selected(request()->query('sort_by') === 'type')>Type</option>
                    <option value="process_name" @selected(request()->query('sort_by') === 'process_name')>Process</option>
                    <option value="src_ip" @selected(request()->query('sort_by') === 'src_ip')>Source IP</option>
                    <option value="dst_ip" @selected(request()->query('sort_by') === 'dst_ip')>Destination IP</option>
                    <option value="bytes_sent" @selected(request()->query('sort_by') === 'bytes_sent')>Bytes Sent</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="events-sort-dir">Direction</label>
                <select id="events-sort-dir" name="sort_dir">
                    <option value="desc" @selected(request()->query('sort_dir', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request()->query('sort_dir') === 'asc')>Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="events-per-page">Per Page</label>
                <select id="events-per-page" name="per_page">
                    <option value="10" @selected((string) request()->query('per_page', '25') === '10')>10</option>
                    <option value="25" @selected((string) request()->query('per_page', '25') === '25')>25</option>
                    <option value="50" @selected((string) request()->query('per_page', '25') === '50')>50</option>
                    <option value="100" @selected((string) request()->query('per_page', '25') === '100')>100</option>
                    <option value="200" @selected((string) request()->query('per_page', '25') === '200')>200</option>
                </select>
            </div>
        </div>

        <div class="controls-actions">
            <button type="submit" class="button-primary">Apply Filters</button>
            <button type="button" class="button-secondary" id="events-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="events-refresh">Refresh</button>
            <span id="events-active-filters" class="active-filter-chips">
                <span class="no-active-filters">No active filters</span>
            </span>
        </div>
    </form>

    <div class="table-scroll">
        <table aria-describedby="events-table-description">
            <caption id="events-table-description" class="visually-hidden">Live events table with filters and sorting</caption>
            <thead>
                <tr>
                    <th scope="col" data-sort="id" class="sortable">ID</th>
                    <th scope="col" data-sort="event_time" class="sortable">Time</th>
                    <th scope="col" data-sort="type" class="sortable">Type</th>
                    <th scope="col" data-sort="severity" class="sortable">Severity</th>
                    <th scope="col" data-sort="process_name" class="sortable">Process</th>
                    <th scope="col">Network</th>
                    <th scope="col" data-sort="alert_type" class="sortable">Alert Type</th>
                    <th scope="col">Description</th>
                    <th scope="col">AI Label</th>
                    <th scope="col">AI Confidence</th>
                </tr>
            </thead>
            <tbody id="events-table-body">
            @forelse($events as $event)
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
                <tr class="empty-row" data-empty-row="1">
                    <td colspan="10">No events found</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="events-count" class="pagination-info">
            Showing {{ $events->firstItem() ?? 0 }} - {{ $events->lastItem() ?? 0 }} of {{ $events->total() }} events
        </p>
        <div class="pagination-group">
            <button type="button" id="events-prev-page">Prev</button>
            <span
                id="events-page-indicator"
                class="pagination-info"
                data-current-page="{{ $events->currentPage() }}"
                data-last-page="{{ max(1, $events->lastPage()) }}"
            >Page {{ $events->currentPage() }} / {{ max(1, $events->lastPage()) }}</span>
            <button type="button" id="events-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module" nonce="{{ $cspNonce ?? '' }}">
(() => {
    console.log('[EVENTS] Script module loaded');
    
    if (typeof window.dldsFetchJson !== 'function') {
        console.error('[EVENTS] window.dldsFetchJson not available - app.js failed');
        document.getElementById('events-loading').textContent = 'JS Init failed - reload';
        return;
    }
    
    const endpoint = '/api/dlds/public/events';
    const form = document.getElementById('events-filter-form');
    const body = document.getElementById('events-table-body');
    const loading = document.getElementById('events-loading');
    const lastUpdated = document.getElementById('events-last-updated');
    const count = document.getElementById('events-count');
    const pageIndicator = document.getElementById('events-page-indicator');
    const prevPageBtn = document.getElementById('events-prev-page');
    const nextPageBtn = document.getElementById('events-next-page');
    const resetBtn = document.getElementById('events-reset-filters');
    const refreshBtn = document.getElementById('events-refresh');
    const activeFilters = document.getElementById('events-active-filters');
    const typeField = form.elements.namedItem('type');
    const alertTypeControl = form.querySelector('[data-filter="alert-type"]');
    const processControl = form.querySelector('[data-filter="process"]');
    const hasFileControl = form.querySelector('[data-filter="has-file"]');
    const sortableHeaders = Array.from(form.closest('.table-shell')?.querySelectorAll('th.sortable') ?? []);

    if (!form || !body || !prevPageBtn || !nextPageBtn) {
        console.error('[EVENTS] Required DOM missing');
        return;
    }
    
    console.log('[EVENTS] DOM ready, attaching listeners');


    const defaults = {
        search: '',
        type: '',
        severity: '',
        alert_type: '',
        process_name: '',
        src_ip: '',
        dst_ip: '',
        date_from: '',
        date_to: '',
        has_file: '',
        sort_by: 'event_time',
        sort_dir: 'desc',
        per_page: '25',
        page: '1',
    };

    const state = { ...defaults };
    let realtimeRefreshTimer = null;

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

    const filterLabels = {
        search: 'Search',
        type: 'Type',
        severity: 'Severity',
        alert_type: 'Alert type',
        process_name: 'Process',
        src_ip: 'Source IP',
        dst_ip: 'Destination IP',
        date_from: 'From',
        date_to: 'To',
        has_file: 'File',
        sort_by: 'Sort',
        sort_dir: 'Direction',
        per_page: 'Per page',
    };

    const renderFilterChips = () => {
        window.dldsRenderFilterChips({
            container: activeFilters,
            state,
            defaults,
            labels: filterLabels,
        });
    };

    const clearControl = (name) => {
        const el = form.elements.namedItem(name);
        if (el) {
            el.value = '';
        }
    };

    const applyTypeVisibility = () => {
        const normalizedType = String(typeField?.value ?? state.type ?? '').toLowerCase();
        const showAlertType = normalizedType === 'alert';
        const showProcessFields = normalizedType === 'process' || normalizedType === 'file';

        alertTypeControl?.classList.toggle('is-hidden', !showAlertType);
        processControl?.classList.toggle('is-hidden', !showProcessFields);
        hasFileControl?.classList.toggle('is-hidden', !showProcessFields);

        if (!showAlertType) {
            clearControl('alert_type');
        }
        if (!showProcessFields) {
            clearControl('process_name');
            clearControl('has_file');
        }
    };

    const buildQuery = () => {
        return window.dldsCleanQuery(state, defaults);
    };

    const writeUrl = () => {
        const params = buildQuery();
        const query = params.toString();
        const url = query === ''
            ? window.location.pathname
            : `${window.location.pathname}?${query}`;

        window.history.replaceState({}, '', url);
    };

    const stampUpdated = (prefix) => {
        if (lastUpdated) {
            lastUpdated.textContent = `${prefix}: ${new Date().toLocaleTimeString()}`;
        }
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
            body.innerHTML = '<tr class="empty-row" data-empty-row="1"><td colspan="10">No events found for the selected filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((event) => {
            const severity = event.severity ?? 'LOW';
            return `
                <tr data-event-id="${Number(event.id)}">
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
            renderFilterChips();
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
            renderFilterChips();
            stampUpdated(reason);
            setLoading('Live stream active');
            window.dldsMarkDataSync('events');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Unable to refresh; showing last known events');
            stampUpdated('Sync retrying');
            window.dldsMarkDataSyncFailure('events');
            console.error('Failed to fetch events:', error);
        }
    };

    const syncStateFromControls = () => {
        Object.keys(defaults).forEach((key) => {
            if (key === 'page') {
                return;
            }

            const el = form.elements.namedItem(key);
            if (el) {
                state[key] = el.value;
            }
        });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyTypeVisibility();
        syncStateFromControls();
        state.page = '1';
        fetchData({ reason: 'Filters applied' });
    });

    typeField?.addEventListener('change', () => {
        applyTypeVisibility();
    });

prevPageBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log('[EVENTS] Prev clicked');
        const current = Number(state.page || '1');
        if (current <= 1) return;
        state.page = String(current - 1);
        fetchData({ reason: 'Page updated' });
    });

    nextPageBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const current = Number(state.page || '1');
        const lastPage = Number(state._lastPage ?? 1);
        if (current >= lastPage) return;
        state.page = String(current + 1);
        fetchData({ reason: 'Page updated' });
    });

    resetBtn.addEventListener('click', () => {
        Object.assign(state, defaults);
        syncControls();
        applyTypeVisibility();
        fetchData({ reason: 'Filters reset' });
    });

    refreshBtn.addEventListener('click', () => {
        fetchData({ reason: 'Manual refresh', pushUrl: false });
    });

    window.addEventListener('dlds:event-created', () => {
        window.clearTimeout(realtimeRefreshTimer);
        realtimeRefreshTimer = window.setTimeout(() => {
            fetchData({ reason: 'Realtime sync', pushUrl: false });
        }, 450);
    });

    window.addEventListener('dlds:auto-refresh', () => {
        fetchData({ reason: 'Auto-refresh sync', pushUrl: false });
    });

    parseUrlState();
    const initialPagination = getInitialPagination();
    state.page = String(initialPagination.page);
    state._lastPage = initialPagination.lastPage;
    syncControls();
    applyTypeVisibility();
    syncStateFromControls();
    renderFilterChips();
    fetchData({ reason: 'Initial sync', pushUrl: false });
})();
</script>
@endsection
