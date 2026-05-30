@extends('layouts.master')

@section('title', 'Processes | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title">Process Monitoring</h2>
        <p class="page-description">Analyze process execution, file interaction, and correlated context without losing realtime visibility.</p>
    </div>
    <div class="page-meta">
        <span id="processes-last-updated">Loaded from server</span>
    </div>
</section>

<div class="card table-shell">
    <div class="card-header">
        <div>
            <h3 class="card-title">Process Explorer</h3>
            <p class="card-subtitle">Filter by process identity, severity, and related telemetry fields.</p>
        </div>
        <p id="processes-loading" class="loading-note" role="status">Ready</p>
    </div>

    <form id="processes-filter-form" class="table-controls" autocomplete="off">
        <div class="filters-grid">
            <div class="control control-search">
                <label for="processes-search">Search</label>
                <input id="processes-search" name="search" type="search" placeholder="ID, PID, process, file path, description..." value="{{ request()->query('search', '') }}">
            </div>

            <div class="control control-medium">
                <label for="processes-severity">Severity</label>
                <select id="processes-severity" name="severity">
                    <option value="" @selected(request()->query('severity', '') === '')>All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}" @selected(request()->query('severity') === $severity->name)>{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="processes-name">Process</label>
                <input id="processes-name" name="process_name" type="text" list="processes-name-list" placeholder="python.exe" value="{{ request()->query('process_name', '') }}">
                <datalist id="processes-name-list">
                    @foreach($processCatalog as $process)
                        <option value="{{ $process->process_name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="control control-small">
                <label for="processes-pid">PID</label>
                <input id="processes-pid" name="pid" type="number" min="0" placeholder="1234" value="{{ request()->query('pid', '') }}">
            </div>

            <div class="control control-medium">
                <label for="processes-has-file">File Presence</label>
                <select id="processes-has-file" name="has_file">
                    <option value="" @selected(request()->query('has_file', '') === '')>All</option>
                    <option value="1" @selected(request()->query('has_file') === '1')>With file</option>
                    <option value="0" @selected(request()->query('has_file') === '0')>Without file</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-date-from">From</label>
                <input id="processes-date-from" name="date_from" type="date" value="{{ request()->query('date_from', '') }}">
            </div>

            <div class="control control-small">
                <label for="processes-date-to">To</label>
                <input id="processes-date-to" name="date_to" type="date" value="{{ request()->query('date_to', '') }}">
            </div>

            <div class="control control-small">
                <label for="processes-sort-by">Sort By</label>
                <select id="processes-sort-by" name="sort_by">
                    <option value="event_time" @selected(request()->query('sort_by', 'event_time') === 'event_time')>Time</option>
                    <option value="process_name" @selected(request()->query('sort_by') === 'process_name')>Process</option>
                    <option value="pid" @selected(request()->query('sort_by') === 'pid')>PID</option>
                    <option value="severity" @selected(request()->query('sort_by') === 'severity')>Severity</option>
                    <option value="file_path" @selected(request()->query('sort_by') === 'file_path')>File Path</option>
                    <option value="id" @selected(request()->query('sort_by') === 'id')>ID</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-sort-dir">Direction</label>
                <select id="processes-sort-dir" name="sort_dir">
                    <option value="desc" @selected(request()->query('sort_dir', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request()->query('sort_dir') === 'asc')>Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-per-page">Per Page</label>
                <select id="processes-per-page" name="per_page">
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
            <button type="button" class="button-secondary" id="processes-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="processes-refresh">Refresh</button>
            <span id="processes-active-filters" class="active-filter-chips">
                <span class="no-active-filters">No active filters</span>
            </span>
        </div>
    </form>

    <div class="table-scroll">
        <table aria-describedby="processes-table-description">
            <caption id="processes-table-description" class="visually-hidden">Process events table with filters and sorting</caption>
            <thead>
                <tr>
                    <th scope="col" data-sort="id" class="sortable">ID</th>
                    <th scope="col" data-sort="event_time" class="sortable">Time</th>
                    <th scope="col" data-sort="type" class="sortable">Type</th>
                    <th scope="col" data-sort="severity" class="sortable">Severity</th>
                    <th scope="col" data-sort="pid" class="sortable">PID</th>
                    <th scope="col" data-sort="process_name" class="sortable">Process</th>
                    <th scope="col">File Path</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody id="processes-table-body">
            @forelse($processes as $row)
                <tr data-event-id="{{ $row->id }}">
                    <td class="table-id" data-label="ID"><span class="cell-value">{{ $row->id }}</span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">{{ $row->event_time?->format('Y-m-d H:i:s') ?? '-' }}</span></td>
                    <td data-label="Type"><span class="cell-value"><span class="type-pill" data-type="{{ strtolower($row->type ?? 'process') }}">{{ strtoupper($row->type ?? 'PROCESS') }}</span></span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="{{ 'severity-' . strtolower($row->severity ?? 'low') }}">{{ $row->severity ?? 'LOW' }}</span></span></td>
                    <td class="mono" data-label="PID"><span class="cell-value">{{ $row->pid ?? 0 }}</span></td>
                    <td data-label="Process"><span class="cell-value">{{ $row->process_name ?? '-' }}</span></td>
                    <td class="mono" data-label="File Path"><span class="cell-value">{{ $row->file_path ?? '-' }}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="{{ $row->description ?? '-' }}">{{ \Illuminate\Support\Str::limit($row->description ?? '-', 110) }}</span></td>
                </tr>
            @empty
                <tr class="empty-row" data-empty-row="1"><td colspan="8">No process data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="processes-count" class="pagination-info">
            Showing {{ $processes->firstItem() ?? 0 }} - {{ $processes->lastItem() ?? 0 }} of {{ $processes->total() }} rows
        </p>
        <div class="pagination-group">
            <button type="button" id="processes-prev-page">Prev</button>
            <span
                id="processes-page-indicator"
                class="pagination-info"
                data-current-page="{{ $processes->currentPage() }}"
                data-last-page="{{ max(1, $processes->lastPage()) }}"
            >Page {{ $processes->currentPage() }} / {{ max(1, $processes->lastPage()) }}</span>
            <button type="button" id="processes-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module" nonce="{{ $cspNonce ?? '' }}">
(() => {
const endpoint = '/api/dlds/public/processes';
    const form = document.getElementById('processes-filter-form');
    const body = document.getElementById('processes-table-body');
    const loading = document.getElementById('processes-loading');
    const lastUpdated = document.getElementById('processes-last-updated');
    const count = document.getElementById('processes-count');
    const pageIndicator = document.getElementById('processes-page-indicator');
    const prevPageBtn = document.getElementById('processes-prev-page');
    const nextPageBtn = document.getElementById('processes-next-page');
    const resetBtn = document.getElementById('processes-reset-filters');
    const refreshBtn = document.getElementById('processes-refresh');
    const activeFilters = document.getElementById('processes-active-filters');
    const sortableHeaders = Array.from(form.closest('.table-shell')?.querySelectorAll('th.sortable') ?? []);

    if (!form || !body) {
        return;
    }

    const defaults = {
        search: '',
        severity: '',
        process_name: '',
        pid: '',
        has_file: '',
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
        process_name: 'Process',
        pid: 'PID',
        has_file: 'File',
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
        const normalized = String(type ?? 'process').toLowerCase();
        return `<span class="type-pill" data-type="${window.dldsEscapeHtml(normalized)}">${window.dldsEscapeHtml(normalized.toUpperCase())}</span>`;
    };

    const renderRows = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr class="empty-row" data-empty-row="1"><td colspan="8">No process data for the selected filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((row) => {
            const severity = row.severity ?? 'LOW';
            return `
                <tr data-event-id="${Number(row.id)}">
                    <td class="table-id" data-label="ID"><span class="cell-value">${Number(row.id)}</span></td>
                    <td class="mono" data-label="Time"><span class="cell-value">${window.dldsEscapeHtml(window.dldsFormatTime(row.event_time))}</span></td>
                    <td data-label="Type"><span class="cell-value">${typePill(row.type)}</span></td>
                    <td data-label="Severity"><span class="cell-value"><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></span></td>
                    <td class="mono" data-label="PID"><span class="cell-value">${Number(row.pid ?? 0)}</span></td>
                    <td data-label="Process"><span class="cell-value">${window.dldsEscapeHtml(row.process_name ?? '-')}</span></td>
                    <td class="mono" data-label="File Path"><span class="cell-value">${window.dldsEscapeHtml(row.file_path ?? '-')}</span></td>
                    <td data-label="Description"><span class="cell-value truncate-text" title="${window.dldsEscapeHtml(row.description ?? '-')}">${window.dldsEscapeHtml(window.dldsClipText(row.description ?? '-', 110))}</span></td>
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
        count.textContent = `Showing ${from} - ${to} of ${total} rows`;
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
        setLoading('Loading process data...');

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
            setLoading('Realtime process feed active');
            window.dldsMarkDataSync('processes');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Unable to refresh; showing last known process data');
            stampUpdated('Sync retrying');
            window.dldsMarkDataSyncFailure('processes');
            console.error('Failed to fetch process data:', error);
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
        syncControls();
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
    renderFilterChips();
    fetchData({ reason: 'Initial sync', pushUrl: false });
})();
</script>
@endsection
