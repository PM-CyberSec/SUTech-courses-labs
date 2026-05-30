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
                <label for="processes-type">Type</label>
                <select id="processes-type" name="type">
                    <option value="">All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}">{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="processes-severity">Severity</label>
                <select id="processes-severity" name="severity">
                    <option value="">All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}">{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="processes-name">Process</label>
                <input id="processes-name" name="process_name" type="text" list="processes-name-list" placeholder="python.exe">
                <datalist id="processes-name-list">
                    @foreach($processCatalog as $process)
                        <option value="{{ $process->process_name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="control control-medium">
                <label for="processes-has-file">File Presence</label>
                <select id="processes-has-file" name="has_file">
                    <option value="">All</option>
                    <option value="1">With file</option>
                    <option value="0">Without file</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-date-from">From</label>
                <input id="processes-date-from" name="date_from" type="date">
            </div>

            <div class="control control-small">
                <label for="processes-date-to">To</label>
                <input id="processes-date-to" name="date_to" type="date">
            </div>

            <div class="control control-small">
                <label for="processes-sort-by">Sort By</label>
                <select id="processes-sort-by" name="sort_by">
                    <option value="event_time">Time</option>
                    <option value="process_name">Process</option>
                    <option value="pid">PID</option>
                    <option value="severity">Severity</option>
                    <option value="file_path">File Path</option>
                    <option value="id">ID</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-sort-dir">Direction</label>
                <select id="processes-sort-dir" name="sort_dir">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="processes-per-page">Per Page</label>
                <select id="processes-per-page" name="per_page">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
        </div>

        <div class="controls-actions">
            <button type="submit" class="button-primary">Apply Filters</button>
            <button type="button" class="button-secondary" id="processes-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="processes-refresh">Refresh</button>
            <span id="processes-active-filters" class="pagination-info">No active filters</span>
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
                    <td class="table-id">{{ $row->id }}</td>
                    <td class="mono">{{ $row->event_time?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td><span class="type-pill" data-type="{{ strtolower($row->type ?? 'process') }}">{{ strtoupper($row->type ?? 'PROCESS') }}</span></td>
                    <td><span class="{{ 'severity-' . strtolower($row->severity ?? 'low') }}">{{ $row->severity ?? 'LOW' }}</span></td>
                    <td class="mono">{{ $row->pid ?? 0 }}</td>
                    <td>{{ $row->process_name ?? '-' }}</td>
                    <td class="mono">{{ $row->file_path ?? '-' }}</td>
                    <td>{{ $row->description ?? '-' }}</td>
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
            <span id="processes-page-indicator" class="pagination-info">Page {{ $processes->currentPage() }} / {{ max(1, $processes->lastPage()) }}</span>
            <button type="button" id="processes-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module">
(() => {
    const endpoint = '/api/dlds/processes';
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
    const searchInput = document.getElementById('processes-search');

    if (!form || !body) {
        return;
    }

    const defaults = {
        search: '',
        type: '',
        severity: '',
        process_name: '',
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

    const updateFilterBadge = () => {
        const active = Object.entries(state)
            .filter(([key, value]) => key !== 'page' && String(value ?? '') !== String(defaults[key] ?? ''))
            .length;

        activeFilters.textContent = active === 0
            ? 'No active filters'
            : `${active} filter${active > 1 ? 's' : ''} active`;
    };

    const buildQuery = () => {
        const params = new URLSearchParams();
        Object.entries(state).forEach(([key, value]) => {
            if (value === '' || value === null || value === undefined) {
                return;
            }
            params.set(key, String(value));
        });

        return params;
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
                    <td class="table-id">${Number(row.id)}</td>
                    <td class="mono">${window.dldsEscapeHtml(window.dldsFormatTime(row.event_time))}</td>
                    <td>${typePill(row.type)}</td>
                    <td><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></td>
                    <td class="mono">${Number(row.pid ?? 0)}</td>
                    <td>${window.dldsEscapeHtml(row.process_name ?? '-')}</td>
                    <td class="mono">${window.dldsEscapeHtml(row.file_path ?? '-')}</td>
                    <td>${window.dldsEscapeHtml(row.description ?? '-')}</td>
                </tr>
            `;
        }).join('');
    };

    const applyMeta = (meta) => {
        const total = Number(meta?.total ?? 0);
        const page = Number(meta?.page ?? 1);
        const lastPage = Number(meta?.last_page ?? 1);
        const from = Number(meta?.from ?? 0);
        const to = Number(meta?.to ?? 0);

        state.page = String(page);
        count.textContent = `Showing ${from} - ${to} of ${total} rows`;
        pageIndicator.textContent = `Page ${page} / ${Math.max(lastPage, 1)}`;
        prevPageBtn.disabled = page <= 1;
        nextPageBtn.disabled = page >= lastPage;
    };

    const updateHeaderArrows = () => {
        document.querySelectorAll('th.sortable').forEach((th) => {
            if (th.dataset.sort === state.sort_by) {
                th.dataset.dir = state.sort_dir;
            } else {
                delete th.dataset.dir;
            }
        });
    };

    document.querySelectorAll('th.sortable').forEach((th) => {
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
            fetchData({ reason: 'Header sort' });
        });
    });

    const fetchData = async ({ reason = 'Synced', pushUrl = true } = {}) => {
        updateHeaderArrows();
        setLoading('Loading process data...');

        try {
            const params = buildQuery();
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            renderRows(payload.data ?? []);
            applyMeta(payload.meta ?? {});
            updateFilterBadge();
            stampUpdated(reason);
            setLoading('Realtime process feed active');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Connection issue, retrying...');
            stampUpdated('Sync retrying');
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

    const debouncedSearch = window.dldsDebounce(() => {
        syncStateFromControls();
        state.page = '1';
        fetchData({ reason: 'Search synced' });
    }, 350);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        syncStateFromControls();
        state.page = '1';
        fetchData({ reason: 'Filters applied' });
    });

    searchInput?.addEventListener('input', debouncedSearch);

    ['sort_by', 'sort_dir', 'per_page'].forEach((name) => {
        const el = form.elements.namedItem(name);
        el?.addEventListener('change', () => {
            syncStateFromControls();
            state.page = '1';
            fetchData({ reason: 'Sorting updated' });
        });
    });

    prevPageBtn.addEventListener('click', () => {
        const current = Number(state.page || '1');
        if (current <= 1) return;
        state.page = String(current - 1);
        fetchData({ reason: 'Page updated' });
    });

    nextPageBtn.addEventListener('click', () => {
        const current = Number(state.page || '1');
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
    syncControls();
    updateFilterBadge();
    fetchData({ reason: 'Initial sync', pushUrl: false });
})();
</script>
@endsection
