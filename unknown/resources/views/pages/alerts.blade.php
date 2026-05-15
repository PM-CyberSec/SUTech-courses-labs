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
                    <option value="">All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}">{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="alerts-type">Event Type</label>
                <select id="alerts-type" name="type">
                    <option value="">All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}">{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="alerts-alert-type">Alert Type</label>
                <select id="alerts-alert-type" name="alert_type">
                    <option value="">All</option>
                    @foreach($alertTypes as $alertType)
                        <option value="{{ $alertType->name }}">{{ $alertType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="alerts-process">Process</label>
                <input id="alerts-process" name="process_name" type="text" placeholder="python.exe">
            </div>

            <div class="control control-medium">
                <label for="alerts-src-ip">Source IP</label>
                <input id="alerts-src-ip" name="src_ip" type="text" placeholder="10.0.0.5">
            </div>

            <div class="control control-medium">
                <label for="alerts-dst-ip">Destination IP</label>
                <input id="alerts-dst-ip" name="dst_ip" type="text" placeholder="8.8.8.8">
            </div>

            <div class="control control-small">
                <label for="alerts-date-from">From</label>
                <input id="alerts-date-from" name="date_from" type="date">
            </div>

            <div class="control control-small">
                <label for="alerts-date-to">To</label>
                <input id="alerts-date-to" name="date_to" type="date">
            </div>

            <div class="control control-small">
                <label for="alerts-sort-by">Sort By</label>
                <select id="alerts-sort-by" name="sort_by">
                    <option value="event_time">Time</option>
                    <option value="severity">Severity</option>
                    <option value="id">ID</option>
                    <option value="alert_type">Alert Type</option>
                    <option value="type">Type</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="alerts-sort-dir">Direction</label>
                <select id="alerts-sort-dir" name="sort_dir">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="alerts-per-page">Per Page</label>
                <select id="alerts-per-page" name="per_page">
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
            <button type="button" class="button-secondary" id="alerts-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="alerts-refresh">Refresh</button>
            <span id="alerts-active-filters" class="pagination-info">No active filters</span>
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
                    <td class="table-id">{{ $alert->id }}</td>
                    <td class="mono">{{ $alert->event_time?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td><span class="type-pill" data-type="{{ strtolower($alert->type ?? 'alert') }}">{{ strtoupper($alert->type ?? 'ALERT') }}</span></td>
                    <td><span class="{{ 'severity-' . strtolower($alert->severity ?? 'low') }}">{{ $alert->severity ?? 'LOW' }}</span></td>
                    <td>{{ $alert->alert_type ?? '-' }}</td>
                    <td class="mono">{{ $alert->src_ip ?? '-' }}:{{ $alert->src_port ?? 0 }}</td>
                    <td class="mono">{{ $alert->dst_ip ?? '-' }}:{{ $alert->dst_port ?? 0 }}</td>
                    <td>{{ $alert->description ?? '-' }}</td>
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
            <span id="alerts-page-indicator" class="pagination-info">Page {{ $alerts->currentPage() }} / {{ max(1, $alerts->lastPage()) }}</span>
            <button type="button" id="alerts-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module">
(() => {
    const endpoint = '/api/dlds/alerts';
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
    const searchInput = document.getElementById('alerts-search');

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
                    <td class="table-id">${Number(alert.id)}</td>
                    <td class="mono">${window.dldsEscapeHtml(window.dldsFormatTime(alert.event_time))}</td>
                    <td>${typePill(alert.type)}</td>
                    <td><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></td>
                    <td>${window.dldsEscapeHtml(alert.alert_type ?? '-')}</td>
                    <td class="mono">${window.dldsEscapeHtml(alert.src_ip ?? '-')}:${Number(alert.src_port ?? 0)}</td>
                    <td class="mono">${window.dldsEscapeHtml(alert.dst_ip ?? '-')}:${Number(alert.dst_port ?? 0)}</td>
                    <td>${window.dldsEscapeHtml(alert.description ?? '-')}</td>
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
        count.textContent = `Showing ${from} - ${to} of ${total} alerts`;
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
        setLoading('Loading alerts...');

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
            setLoading('Realtime alerts active');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Connection issue, retrying...');
            stampUpdated('Sync retrying');
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
