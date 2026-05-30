@extends('layouts.master')

@section('title', 'Network Activity | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title">Network Activity</h2>
        <p class="page-description">Inspect source/destination traffic with granular filtering and sortable telemetry fields.</p>
    </div>
    <div class="page-meta">
        <span id="network-last-updated">Loaded from server</span>
    </div>
</section>

<div class="card table-shell">
    <div class="card-header">
        <div>
            <h3 class="card-title">Network Explorer</h3>
            <p class="card-subtitle">Track inbound/outbound flows and abnormal traffic volume changes.</p>
        </div>
        <p id="network-loading" class="loading-note" role="status">Ready</p>
    </div>

    <form id="network-filter-form" class="table-controls" autocomplete="off">
        <div class="filters-grid">
            <div class="control control-search">
                <label for="network-search">Search</label>
                <input id="network-search" name="search" type="search" placeholder="ID, IP, bytes, description, process..." value="{{ request()->query('search', '') }}">
            </div>

            <div class="control control-medium">
                <label for="network-type">Type</label>
                <select id="network-type" name="type">
                    <option value="">All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}">{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="network-severity">Severity</label>
                <select id="network-severity" name="severity">
                    <option value="">All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}">{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="network-src-ip">Source IP</label>
                <input id="network-src-ip" name="src_ip" type="text" placeholder="10.0.0.5">
            </div>

            <div class="control control-medium">
                <label for="network-dst-ip">Destination IP</label>
                <input id="network-dst-ip" name="dst_ip" type="text" placeholder="8.8.8.8">
            </div>

            <div class="control control-small">
                <label for="network-date-from">From</label>
                <input id="network-date-from" name="date_from" type="date">
            </div>

            <div class="control control-small">
                <label for="network-date-to">To</label>
                <input id="network-date-to" name="date_to" type="date">
            </div>

            <div class="control control-small">
                <label for="network-sort-by">Sort By</label>
                <select id="network-sort-by" name="sort_by">
                    <option value="event_time">Time</option>
                    <option value="bytes_sent">Bytes</option>
                    <option value="src_ip">Source IP</option>
                    <option value="dst_ip">Destination IP</option>
                    <option value="id">ID</option>
                    <option value="severity">Severity</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="network-sort-dir">Direction</label>
                <select id="network-sort-dir" name="sort_dir">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="network-per-page">Per Page</label>
                <select id="network-per-page" name="per_page">
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
            <button type="button" class="button-secondary" id="network-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="network-refresh">Refresh</button>
            <span id="network-active-filters" class="pagination-info">No active filters</span>
        </div>
    </form>

    <div class="table-scroll">
        <table aria-describedby="network-table-description">
            <caption id="network-table-description" class="visually-hidden">Network events table with filters and sorting</caption>
            <thead>
                <tr>
                    <th scope="col" data-sort="id" class="sortable">ID</th>
                    <th scope="col" data-sort="event_time" class="sortable">Time</th>
                    <th scope="col" data-sort="type" class="sortable">Type</th>
                    <th scope="col" data-sort="severity" class="sortable">Severity</th>
                    <th scope="col">Source</th>
                    <th scope="col">Destination</th>
                    <th scope="col">Ports</th>
                    <th scope="col">Bytes</th>
                </tr>
            </thead>
            <tbody id="network-table-body">
            @forelse($network as $row)
                <tr data-event-id="{{ $row->id }}">
                    <td class="table-id">{{ $row->id }}</td>
                    <td class="mono">{{ $row->event_time?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td><span class="type-pill" data-type="{{ strtolower($row->type ?? 'network') }}">{{ strtoupper($row->type ?? 'NETWORK') }}</span></td>
                    <td><span class="{{ 'severity-' . strtolower($row->severity ?? 'low') }}">{{ $row->severity ?? 'LOW' }}</span></td>
                    <td class="mono">{{ $row->src_ip ?? '-' }}</td>
                    <td class="mono">{{ $row->dst_ip ?? '-' }}</td>
                    <td class="mono">{{ $row->src_port ?? 0 }} → {{ $row->dst_port ?? 0 }}</td>
                    <td class="mono">{{ number_format($row->bytes_sent ?? 0) }}</td>
                </tr>
            @empty
                <tr class="empty-row" data-empty-row="1"><td colspan="8">No network data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="network-count" class="pagination-info">
            Showing {{ $network->firstItem() ?? 0 }} - {{ $network->lastItem() ?? 0 }} of {{ $network->total() }} rows
        </p>
        <div class="pagination-group">
            <button type="button" id="network-prev-page">Prev</button>
            <span id="network-page-indicator" class="pagination-info">Page {{ $network->currentPage() }} / {{ max(1, $network->lastPage()) }}</span>
            <button type="button" id="network-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module">
(() => {
    const endpoint = '/api/dlds/network';
    const form = document.getElementById('network-filter-form');
    const body = document.getElementById('network-table-body');
    const loading = document.getElementById('network-loading');
    const lastUpdated = document.getElementById('network-last-updated');
    const count = document.getElementById('network-count');
    const pageIndicator = document.getElementById('network-page-indicator');
    const prevPageBtn = document.getElementById('network-prev-page');
    const nextPageBtn = document.getElementById('network-next-page');
    const resetBtn = document.getElementById('network-reset-filters');
    const refreshBtn = document.getElementById('network-refresh');
    const activeFilters = document.getElementById('network-active-filters');
    const searchInput = document.getElementById('network-search');

    if (!form || !body) {
        return;
    }

    const defaults = {
        search: '',
        type: '',
        severity: '',
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
        const normalized = String(type ?? 'network').toLowerCase();
        return `<span class="type-pill" data-type="${window.dldsEscapeHtml(normalized)}">${window.dldsEscapeHtml(normalized.toUpperCase())}</span>`;
    };

    const renderRows = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr class="empty-row" data-empty-row="1"><td colspan="8">No network data for the selected filters</td></tr>';
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
                    <td class="mono">${window.dldsEscapeHtml(row.src_ip ?? '-')}</td>
                    <td class="mono">${window.dldsEscapeHtml(row.dst_ip ?? '-')}</td>
                    <td class="mono">${Number(row.src_port ?? 0)} → ${Number(row.dst_port ?? 0)}</td>
                    <td class="mono">${Number(row.bytes_sent ?? 0).toLocaleString()}</td>
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
        setLoading('Loading network data...');

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
            setLoading('Realtime network feed active');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Connection issue, retrying...');
            stampUpdated('Sync retrying');
            console.error('Failed to fetch network data:', error);
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
