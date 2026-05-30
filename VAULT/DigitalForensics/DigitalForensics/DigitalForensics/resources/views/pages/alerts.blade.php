@extends('layouts.master')

@section('title', 'Alerts | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title">Live Alerts</h2>
        <p class="page-description">Alert-focused stream including high and critical severity incidents.</p>
    </div>
    <div class="page-meta">
        <span id="alerts-last-updated">Loaded from server</span>
    </div>
</section>

<div class="card table-shell">
    <div class="card-header">
        <div>
            <h3 class="card-title">Alert Queue</h3>
            <p class="card-subtitle">Use filters to narrow incident classes and response priorities.</p>
        </div>
        <p id="alerts-loading" class="loading-note" role="status">Ready</p>
    </div>

    <form id="alerts-filter-form" class="table-controls" autocomplete="off">
        <div class="filters-grid">
            <div class="control control-search">
                <label for="alerts-search">Search</label>
                <input id="alerts-search" name="search" type="search" placeholder="ID, alert type, description, IP, process..." value="{{ request()->query('search', '') }}">
            </div>

            <div class="control control-medium">
                <label for="alerts-severity">Severity</label>
                <select id="alerts-severity" name="severity">
                    <option value="" @selected(request()->query('severity', '') === '')>All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}" @selected(request()->query('severity') === $severity->name)>{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="alerts-type">Event Type</label>
                <select id="alerts-type" name="type">
                    <option value="" @selected(request()->query('type', '') === '')>All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}" @selected(request()->query('type') === $eventType->name)>{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="alerts-alert-type">Alert Type</label>
                <select id="alerts-alert-type" name="alert_type">
                    <option value="" @selected(request()->query('alert_type', '') === '')>All</option>
                    @foreach($alertTypes as $alertType)
                        <option value="{{ $alertType->name }}" @selected(request()->query('alert_type') === $alertType->name)>{{ $alertType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium" data-filter="process">
                <label for="alerts-process">Process</label>
                <input id="alerts-process" name="process_name" type="text" placeholder="python.exe" value="{{ request()->query('process_name', '') }}">
            </div>

            <div class="control control-medium">
                <label for="alerts-src-ip">Source IP</label>
                <input id="alerts-src-ip" name="src_ip" type="text" placeholder="10.0.0.5" value="{{ request()->query('src_ip', '') }}">
            </div>

            <div class="control control-medium">
                <label for="alerts-dst-ip">Destination IP</label>
                <input id="alerts-dst-ip" name="dst_ip" type="text" placeholder="8.8.8.8" value="{{ request()->query('dst_ip', '') }}">
            </div>

            <div class="control control-small">
                <label for="alerts-date-from">From</label>
                <input id="alerts-date-from" name="date_from" type="date" value="{{ request()->query('date_from', '') }}">
            </div>

            <div class="control control-small">
                <label for="alerts-date-to">To</label>
                <input id="alerts-date-to" name="date_to" type="date" value="{{ request()->query('date_to', '') }}">
            </div>

            <div class="control control-small">
                <label for="alerts-sort-by">Sort By</label>
                <select id="alerts-sort-by" name="sort_by">
                    <option value="event_time" @selected(request()->query('sort_by', 'event_time') === 'event_time')>Time</option>
                    <option value="severity" @selected(request()->query('sort_by') === 'severity')>Severity</option>
                    <option value="id" @selected(request()->query('sort_by') === 'id')>ID</option>
                    <option value="alert_type" @selected(request()->query('sort_by') === 'alert_type')>Alert Type</option>
                    <option value="type" @selected(request()->query('sort_by') === 'type')>Type</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="alerts-sort-dir">Direction</label>
                <select id="alerts-sort-dir" name="sort_dir">
                    <option value="desc" @selected(request()->query('sort_dir', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request()->query('sort_dir') === 'asc')>Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="alerts-per-page">Per Page</label>
                <select id="alerts-per-page" name="per_page">
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
            <button type="button" class="button-secondary" id="alerts-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="alerts-refresh">Refresh</button>
            <span id="alerts-active-filters" class="active-filter-chips">
                <span class="no-active-filters">No active filters</span>
            </span>
        </div>
    </form>

    <div class="table-scroll">
        <table aria-describedby="alerts-table-description">
            <caption id="alerts-table-description" class="visually-hidden">Live alerts table with filters and sorting</caption>
            <thead>
                <tr>
                    <th scope="col" data-sort="id" class="sortable">ID</th>
                    <th scope="col" data-sort="event_time" class="sortable">Time</th>
                    <th scope="col" data-sort="type" class="sortable">Type</th>
                    <th scope="col" data-sort="severity" class="sortable">Severity</th>
                    <th scope="col" data-sort="alert_type" class="sortable">Alert Type</th>
                    <th scope="col">Source</th>
                    <th scope="col">Destination</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody id="alerts-table-body">
            @forelse($alerts as $alert)
                <tr data-event-id="{{ $alert->id }}">
                    <td class="table-id" data-label="ID"><span class="cell-value">{{ $alert->id }}</span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">{{ $alert->event_time?->format('Y-m-d H:i:s') ?? '-' }}</span></td>
                    <td data-label="Type"><span class="cell-value"><span class="type-pill" data-type="{{ strtolower($alert->type ?? 'alert') }}">{{ strtoupper($alert->type ?? 'ALERT') }}</span></span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="{{ 'severity-' . strtolower($alert->severity ?? 'low') }}">{{ $alert->severity ?? 'LOW' }}</span></span></td>
                    <td data-label="Alert Type"><span class="cell-value">{{ $alert->alert_type ?? '-' }}</span></td>
                    <td class="mono" data-label="Source"><span class="cell-value">{{ $alert->src_ip ?? '-' }}:{{ $alert->src_port ?? 0 }}</span></td>
                    <td class="mono" data-label="Destination"><span class="cell-value">{{ $alert->dst_ip ?? '-' }}:{{ $alert->dst_port ?? 0 }}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="{{ $alert->description ?? '-' }}">{{ \Illuminate\Support\Str::limit($alert->description ?? '-', 110) }}</span></td>
                </tr>
            @empty
                <tr class="empty-row" data-empty-row="1">
                    <td colspan="8">No alerts found</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="alerts-count" class="pagination-info">
            Showing {{ $alerts->firstItem() ?? 0 }} - {{ $alerts->lastItem() ?? 0 }} of {{ $alerts->total() }} alerts
        </p>
        <div class="pagination-group">
            <button type="button" id="alerts-prev-page">Prev</button>
            <span
                id="alerts-page-indicator"
                class="pagination-info"
                data-current-page="{{ $alerts->currentPage() }}"
                data-last-page="{{ max(1, $alerts->lastPage()) }}"
            >Page {{ $alerts->currentPage() }} / {{ max(1, $alerts->lastPage()) }}</span>
            <button type="button" id="alerts-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module" nonce="{{ $cspNonce ?? '' }}">
(() => {
const endpoint = '/api/dlds/public/alerts';
    const form = document.getElementById('alerts-filter-form');
    const body = document.getElementById('alerts-table-body');
    const loading = document.getElementById('alerts-loading');
    const lastUpdated = document.getElementById('alerts-last-updated');
    const count = document.getElementById('alerts-count');
    const pageIndicator = document.getElementById('alerts-page-indicator');
    const prevPageBtn = document.getElementById('alerts-prev-page');
    const nextPageBtn = document.getElementById('alerts-next-page');
    const resetBtn = document.getElementById('alerts-reset-filters');
    const refreshBtn = document.getElementById('alerts-refresh');
    const activeFilters = document.getElementById('alerts-active-filters');
    const typeField = form.elements.namedItem('type');
    const alertTypeField = form.elements.namedItem('alert_type');
    const processControl = form.querySelector('[data-filter="process"]');
    const sortableHeaders = Array.from(form.closest('.table-shell')?.querySelectorAll('th.sortable') ?? []);

    if (!form || !body) {
        return;
    }

    const defaults = {
        search: '',
        severity: '',
        type: '',
        alert_type: '',
        process_name: '',
        src_ip: '',
        dst_ip: '',
        date_from: '',
        date_to: '',
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
        severity: 'Severity',
        type: 'Event type',
        alert_type: 'Alert type',
        process_name: 'Process',
        src_ip: 'Source IP',
        dst_ip: 'Destination IP',
        date_from: 'From',
        date_to: 'To',
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

    const processRelatedAlertType = (value) => {
        const normalized = String(value ?? '').toLowerCase();
        return normalized.includes('process') || normalized.includes('file') || normalized.includes('exec');
    };

    const applyProcessVisibility = () => {
        const normalizedType = String(typeField?.value ?? state.type ?? '').toLowerCase();
        const alertTypeValue = alertTypeField?.value ?? state.alert_type;
        const showProcess = normalizedType === 'process' || normalizedType === 'file' || processRelatedAlertType(alertTypeValue);
        processControl?.classList.toggle('is-hidden', !showProcess);

        if (!showProcess) {
            const processInput = form.elements.namedItem('process_name');
            if (processInput) {
                processInput.value = '';
            }
        }
    };

    const buildQuery = () => {
        return window.dldsCleanQuery(state, defaults);
    };

    const writeUrl = () => {
        const params = buildQuery();
        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
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
        const normalized = String(type ?? 'alert').toLowerCase();
        return `<span class="type-pill" data-type="${window.dldsEscapeHtml(normalized)}">${window.dldsEscapeHtml(normalized.toUpperCase())}</span>`;
    };

    const renderRows = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr class="empty-row" data-empty-row="1"><td colspan="8">No alerts found for the selected filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((alert) => {
            const severity = alert.severity ?? 'LOW';
            return `
                <tr data-event-id="${Number(alert.id)}">
                    <td class="table-id" data-label="ID"><span class="cell-value">${Number(alert.id)}</span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">${window.dldsEscapeHtml(window.dldsFormatTime(alert.event_time))}</span></td>
                    <td data-label="Type"><span class="cell-value">${typePill(alert.type)}</span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></span></td>
                    <td data-label="Alert Type"><span class="cell-value">${window.dldsEscapeHtml(alert.alert_type ?? '-')}</span></td>
                    <td class="mono" data-label="Source"><span class="cell-value">${window.dldsEscapeHtml(alert.src_ip ?? '-')}:${Number(alert.src_port ?? 0)}</span></td>
                    <td class="mono" data-label="Destination"><span class="cell-value">${window.dldsEscapeHtml(alert.dst_ip ?? '-')}:${Number(alert.dst_port ?? 0)}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="${window.dldsEscapeHtml(alert.description ?? '-')}">${window.dldsEscapeHtml(window.dldsClipText(alert.description ?? '-', 110))}</span></td>
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
        count.textContent = `Showing ${from} - ${to} of ${total} alerts`;
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
        setLoading('Loading alerts...');

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
            setLoading('Realtime alerts active');
            window.dldsMarkDataSync('alerts');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Unable to refresh; showing last known alerts');
            stampUpdated('Sync retrying');
            window.dldsMarkDataSyncFailure('alerts');
            console.error('Failed to fetch alerts:', error);
        }
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

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyProcessVisibility();
        syncStateFromControls();
        state.page = '1';
        fetchData({ reason: 'Filters applied' });
    });

    [typeField, alertTypeField].forEach((field) => {
        field?.addEventListener('change', () => {
            applyProcessVisibility();
        });
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
        syncControls();
        applyProcessVisibility();
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
    applyProcessVisibility();
    syncStateFromControls();
    renderFilterChips();
    fetchData({ reason: 'Initial sync', pushUrl: false });
})();
</script>
@endsection
