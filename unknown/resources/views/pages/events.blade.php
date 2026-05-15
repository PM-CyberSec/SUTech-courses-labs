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
                    <option value="">All</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType->name }}">{{ strtoupper($eventType->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="events-severity">Severity</label>
                <select id="events-severity" name="severity">
                    <option value="">All</option>
                    @foreach($severityLevels as $severity)
                        <option value="{{ $severity->name }}">{{ $severity->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="events-alert-type">Alert Type</label>
                <select id="events-alert-type" name="alert_type">
                    <option value="">All</option>
                    @foreach($alertTypes as $alertType)
                        <option value="{{ $alertType->name }}">{{ $alertType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="control control-medium">
                <label for="events-has-file">File Presence</label>
                <select id="events-has-file" name="has_file">
                    <option value="">All</option>
                    <option value="1">With file</option>
                    <option value="0">Without file</option>
                </select>
            </div>

            <div class="control control-medium">
                <label for="events-process">Process</label>
                <input id="events-process" name="process_name" type="text" list="events-process-list" placeholder="python.exe">
                <datalist id="events-process-list">
                    @foreach($processCatalog as $process)
                        <option value="{{ $process->process_name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="control control-medium">
                <label for="events-src-ip">Source IP</label>
                <input id="events-src-ip" name="src_ip" type="text" placeholder="10.0.0.5">
            </div>

            <div class="control control-medium">
                <label for="events-dst-ip">Destination IP</label>
                <input id="events-dst-ip" name="dst_ip" type="text" placeholder="8.8.8.8">
            </div>

            <div class="control control-small">
                <label for="events-date-from">From</label>
                <input id="events-date-from" name="date_from" type="date">
            </div>

            <div class="control control-small">
                <label for="events-date-to">To</label>
                <input id="events-date-to" name="date_to" type="date">
            </div>

            <div class="control control-small">
                <label for="events-sort-by">Sort By</label>
                <select id="events-sort-by" name="sort_by">
                    <option value="event_time">Time</option>
                    <option value="id">ID</option>
                    <option value="severity">Severity</option>
                    <option value="type">Type</option>
                    <option value="process_name">Process</option>
                    <option value="src_ip">Source IP</option>
                    <option value="dst_ip">Destination IP</option>
                    <option value="bytes_sent">Bytes Sent</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="events-sort-dir">Direction</label>
                <select id="events-sort-dir" name="sort_dir">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="events-per-page">Per Page</label>
                <select id="events-per-page" name="per_page">
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
            <button type="button" class="button-secondary" id="events-reset-filters">Reset</button>
            <button type="button" class="button-ghost" id="events-refresh">Refresh</button>
            <span id="events-active-filters" class="pagination-info">No active filters</span>
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
                </tr>
            </thead>
            <tbody id="events-table-body">
            @forelse($events as $event)
                <tr data-event-id="{{ $event->id }}">
                    <td class="table-id">{{ $event->id }}</td>
                    <td class="mono">{{ $event->event_time?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td><span class="type-pill" data-type="{{ strtolower($event->type ?? 'event') }}">{{ strtoupper($event->type ?? 'event') }}</span></td>
                    <td><span class="{{ 'severity-' . strtolower($event->severity ?? 'low') }}">{{ $event->severity ?? 'LOW' }}</span></td>
                    <td><span class="mono">{{ $event->pid }}</span> {{ $event->process_name ?? '-' }}</td>
                    <td class="mono">{{ $event->src_ip ?? '-' }}:{{ $event->src_port ?? 0 }} → {{ $event->dst_ip ?? '-' }}:{{ $event->dst_port ?? 0 }}</td>
                    <td>{{ $event->alert_type ?? '-' }}</td>
                    <td>{{ $event->description ?? '-' }}</td>
                </tr>
            @empty
                <tr class="empty-row" data-empty-row="1">
                    <td colspan="8">No events found</td>
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
            <span id="events-page-indicator" class="pagination-info">Page {{ $events->currentPage() }} / {{ max(1, $events->lastPage()) }}</span>
            <button type="button" id="events-next-page">Next</button>
        </div>
    </div>
</div>

<script type="module">
(() => {
    const endpoint = '/api/dlds/events';
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
    const searchInput = document.getElementById('events-search');

    if (!form || !body) {
        return;
    }

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
            .filter(([key, value]) => {
                if (key === 'page') return false;
                return String(value ?? '') !== String(defaults[key] ?? '');
            })
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
            body.innerHTML = '<tr class="empty-row" data-empty-row="1"><td colspan="8">No events found for the selected filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((event) => {
            const severity = event.severity ?? 'LOW';
            return `
                <tr data-event-id="${Number(event.id)}">
                    <td class="table-id">${Number(event.id)}</td>
                    <td class="mono">${window.dldsEscapeHtml(window.dldsFormatTime(event.event_time))}</td>
                    <td>${typePill(event.type)}</td>
                    <td><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></td>
                    <td><span class="mono">${Number(event.pid ?? 0)}</span> ${window.dldsEscapeHtml(event.process_name ?? '-')}</td>
                    <td class="mono">${window.dldsEscapeHtml(event.src_ip ?? '-')}:${Number(event.src_port ?? 0)} → ${window.dldsEscapeHtml(event.dst_ip ?? '-')}:${Number(event.dst_port ?? 0)}</td>
                    <td>${window.dldsEscapeHtml(event.alert_type ?? '-')}</td>
                    <td>${window.dldsEscapeHtml(event.description ?? '-')}</td>
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
        count.textContent = `Showing ${from} - ${to} of ${total} events`;
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
        setLoading('Loading events...');

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
            setLoading('Live stream active');

            if (pushUrl) {
                writeUrl();
            }
        } catch (error) {
            setLoading('Connection issue, retrying...');
            stampUpdated('Sync retrying');
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
