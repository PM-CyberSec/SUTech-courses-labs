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
                <input id="dashboard-events-search" name="search" type="search" placeholder="ID, type, severity, process, IP, description...">
            </div>

            <div class="control control-medium">
                <label for="dashboard-events-type">Type</label>
                <select id="dashboard-events-type" name="type">
                    <option value="">All</option>
                    <option value="alert">ALERT</option>
                    <option value="network">NETWORK</option>
                    <option value="process">PROCESS</option>
                </select>
            </div>

            <div class="control control-medium">
                <label for="dashboard-events-severity">Severity</label>
                <select id="dashboard-events-severity" name="severity">
                    <option value="">All</option>
                    <option value="LOW">LOW</option>
                    <option value="MEDIUM">MEDIUM</option>
                    <option value="HIGH">HIGH</option>
                    <option value="CRITICAL">CRITICAL</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-sort">Sort By</label>
                <select id="dashboard-events-sort" name="sort_by">
                    <option value="event_time">Time</option>
                    <option value="severity">Severity</option>
                    <option value="id">ID</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-dir">Direction</label>
                <select id="dashboard-events-dir" name="sort_dir">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </select>
            </div>

            <div class="control control-small">
                <label for="dashboard-events-per-page">Per Page</label>
                <select id="dashboard-events-per-page" name="per_page">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>

        <div class="controls-actions">
            <button type="submit" class="button-primary">Apply</button>
            <button type="button" class="button-secondary" id="dashboard-events-reset">Reset</button>
            <a class="chip-link" href="{{ route('events.index') }}">Open Full Events Explorer</a>
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
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody id="dashboard-events-body">
                <tr class="empty-row">
                    <td colspan="7">Loading events...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <p id="dashboard-events-count" class="pagination-info">Showing 0 - 0 of 0 events</p>
        <div class="pagination-group">
            <button type="button" id="dashboard-events-prev">Prev</button>
            <span id="dashboard-events-page" class="pagination-info">Page 1 / 1</span>
            <button type="button" id="dashboard-events-next">Next</button>
        </div>
    </div>
</div>

<script type="module">
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
            const response = await fetch('/api/dlds/stats', {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                applyStats(await response.json());
            }
        } catch (error) {
            stampUpdatedAt('Sync retrying');
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
    const endpoint = '/api/dlds/events';
    const form = document.getElementById('dashboard-events-form');
    const body = document.getElementById('dashboard-events-body');
    const loading = document.getElementById('dashboard-events-loading');
    const count = document.getElementById('dashboard-events-count');
    const pageIndicator = document.getElementById('dashboard-events-page');
    const prevPageBtn = document.getElementById('dashboard-events-prev');
    const nextPageBtn = document.getElementById('dashboard-events-next');
    const resetBtn = document.getElementById('dashboard-events-reset');
    const searchInput = document.getElementById('dashboard-events-search');

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
            body.innerHTML = '<tr class="empty-row"><td colspan="7">No events match the current dashboard filters</td></tr>';
            return;
        }

        body.innerHTML = rows.map((event) => {
            const severity = event.severity ?? 'LOW';
            return `
                <tr>
                    <td class="table-id">${Number(event.id)}</td>
                    <td class="mono">${window.dldsEscapeHtml(window.dldsFormatTime(event.event_time))}</td>
                    <td>${typePill(event.type)}</td>
                    <td><span class="${window.dldsSeverityClass(severity)}">${window.dldsEscapeHtml(severity)}</span></td>
                    <td><span class="mono">${Number(event.pid ?? 0)}</span> ${window.dldsEscapeHtml(event.process_name ?? '-')}</td>
                    <td class="mono">${window.dldsEscapeHtml(event.src_ip ?? '-')} → ${window.dldsEscapeHtml(event.dst_ip ?? '-')}</td>
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

    const buildQuery = () => {
        const params = new URLSearchParams();
        Object.entries(state).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                params.set(key, String(value));
            }
        });

        return params;
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

    const fetchData = async ({ reason = 'Synced' } = {}) => {
        updateHeaderArrows();
        setLoading('Loading events...');

        try {
            const response = await fetch(`${endpoint}?${buildQuery().toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            renderRows(payload.data ?? []);
            applyMeta(payload.meta ?? {});
            setLoading(`Quick view ${reason.toLowerCase()}`);
        } catch (error) {
            setLoading('Connection issue, retrying...');
            console.error('Failed to fetch dashboard event table:', error);
        }
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

    ['sort_by', 'sort_dir', 'per_page', 'type', 'severity'].forEach((name) => {
        const el = form.elements.namedItem(name);
        el?.addEventListener('change', () => {
            syncStateFromControls();
            state.page = '1';
            fetchData({ reason: 'Updated' });
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

    fetchData({ reason: 'Initial sync' });
})();
</script>
@endsection
