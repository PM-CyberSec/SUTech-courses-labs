import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import 'particles.js';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1';
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const reverbForceTls = reverbScheme === 'https';
const reverbChannel = 'dlds-events';
const reverbCreatedEvent = 'dlds.event.created';
const reverbUpdatedEvent = 'event.updated';

window.dldsRealtime = {
    enabled: false,
    connected: false,
    channel: reverbChannel,
    event: reverbCreatedEvent,
    status: 'connecting',
    lastError: null,
};

const emitRealtimeStateChange = () => {
    window.dispatchEvent(new CustomEvent('dlds:realtime-state', {
        detail: { ...window.dldsRealtime },
    }));
};

const updateRealtimeState = (state) => {
    window.dldsRealtime = {
        ...window.dldsRealtime,
        ...state,
    };

    emitRealtimeStateChange();
};

window.dldsFeedHealth = {
    lastSyncAt: null,
    lastFailureAt: null,
    lastSource: null,
};

window.dldsMarkDataSync = (source = 'unknown') => {
    window.dldsFeedHealth = {
        ...window.dldsFeedHealth,
        lastSyncAt: new Date().toISOString(),
        lastFailureAt: null,
        lastSource: source,
    };

    window.dispatchEvent(new CustomEvent('dlds:data-sync', {
        detail: { ...window.dldsFeedHealth },
    }));
};

window.dldsMarkDataSyncFailure = (reason = 'unknown') => {
    window.dldsFeedHealth = {
        ...window.dldsFeedHealth,
        lastFailureAt: new Date().toISOString(),
        lastSource: reason,
    };

    window.dispatchEvent(new CustomEvent('dlds:data-sync-failure', {
        detail: { ...window.dldsFeedHealth },
    }));
};

window.dldsFormatClock = (value) => {
    if (!value) {
        return '--:--:--';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '--:--:--';
    }

    return date.toLocaleTimeString();
};

const computeLiveFeedStatus = (state) => {
    const now = Date.now();
    const lastSyncMs = window.dldsFeedHealth.lastSyncAt
        ? Date.parse(window.dldsFeedHealth.lastSyncAt)
        : null;
    const lastFailureMs = window.dldsFeedHealth.lastFailureAt
        ? Date.parse(window.dldsFeedHealth.lastFailureAt)
        : null;

    if (!lastSyncMs) {
        if (lastFailureMs) {
            return { pillState: 'offline', text: 'OFFLINE' };
        }

        if (state?.connected) {
            return { pillState: 'connected', text: 'CONNECTED' };
        }

        return { pillState: 'connecting', text: 'CONNECTING' };
    }

    const ageMs = now - lastSyncMs;
    const hasRecentFailure = lastFailureMs && lastFailureMs >= lastSyncMs;

    if (ageMs <= 15_000) {
        if (hasRecentFailure && !state?.connected) {
            return { pillState: 'degraded', text: 'DEGRADED' };
        }

        return {
            pillState: 'connected',
            text: state?.connected ? 'CONNECTED' : 'POLLING',
        };
    }

    if (ageMs > 45_000) {
        if (hasRecentFailure) {
            return { pillState: 'offline', text: 'OFFLINE' };
        }

        return { pillState: 'degraded', text: 'DEGRADED' };
    }

    return {
        pillState: state?.connected ? 'connected' : 'degraded',
        text: state?.connected ? 'CONNECTED' : 'POLLING',
    };
};

const setLivePillState = (state) => {
    const pill = document.getElementById('realtime-pill');
    const syncText = document.getElementById('realtime-last-sync');
    if (!pill) {
        return;
    }

    const feedState = computeLiveFeedStatus(state);
    pill.dataset.state = feedState.pillState;
    pill.textContent = feedState.text;

    if (syncText) {
        syncText.textContent = `Last sync: ${window.dldsFormatClock(window.dldsFeedHealth.lastSyncAt)}`;
    }
};

const emitRealtimeEvent = (name, payload) => {
    const eventPayload = payload?.event && typeof payload.event === 'object'
        ? payload.event
        : null;

    if (!eventPayload) {
        console.warn('Received realtime payload without an event object:', payload);
        return;
    }

    window.dispatchEvent(new CustomEvent(name, {
        detail: {
            event: eventPayload,
            raw: payload,
            received_at: new Date().toISOString(),
        },
    }));

    window.dldsMarkDataSync('realtime');
};

window.dldsExtractRealtimeEvent = (detail) => {
    if (detail?.event && typeof detail.event === 'object') {
        return detail.event;
    }

    if (detail && typeof detail === 'object' && 'id' in detail) {
        return detail;
    }

    return null;
};

window.dldsHasRealtimeConnection = () => Boolean(window.dldsRealtime?.connected);
window.dldsSubscribeToEventStream = (callbacks = {}) => {
    const cleanups = [];

    if (typeof callbacks.onCreated === 'function') {
        const handler = (event) => callbacks.onCreated(window.dldsExtractRealtimeEvent(event.detail), event.detail);
        window.addEventListener('dlds:event-created', handler);
        cleanups.push(() => window.removeEventListener('dlds:event-created', handler));
    }

    if (typeof callbacks.onUpdated === 'function') {
        const handler = (event) => callbacks.onUpdated(window.dldsExtractRealtimeEvent(event.detail), event.detail);
        window.addEventListener('dlds:event-updated', handler);
        cleanups.push(() => window.removeEventListener('dlds:event-updated', handler));
    }

    return () => {
        cleanups.forEach((cleanup) => cleanup());
    };
};

window.dldsDebounce = (callback, waitMs = 300) => {
    let timer = null;

    return (...args) => {
        if (timer) {
            window.clearTimeout(timer);
        }

        timer = window.setTimeout(() => {
            callback(...args);
        }, waitMs);
    };
};

if (reverbKey) {
    try {
        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: '127.0.0.1',
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbForceTls,
            enabledTransports: ['ws'],
        });

        updateRealtimeState({
            enabled: true,
            status: 'connecting',
        });

        const connection = window.Echo?.connector?.pusher?.connection;
        if (connection && typeof connection.bind === 'function') {
            connection.bind('connected', () => {
                updateRealtimeState({ connected: true, status: 'connected', lastError: null });
                console.info(`[DLDS] Reverb connected to ${reverbHost}:${reverbPort}`);
            });

            connection.bind('disconnected', () => {
                updateRealtimeState({ connected: false, status: 'disconnected' });
                console.warn('[DLDS] Reverb disconnected. Polling fallback remains active.');
            });

            connection.bind('unavailable', () => {
                updateRealtimeState({ connected: false, status: 'disconnected' });
                console.warn('[DLDS] Reverb is unavailable. Verify reverb:start is running.');
            });

            connection.bind('error', (error) => {
                updateRealtimeState({
                    connected: false,
                    status: 'error',
                    lastError: error,
                });
                window.dldsMarkDataSyncFailure('realtime-error');
                console.error('[DLDS] Reverb connection error:', error);
            });
        }

        const reverbStream = window.Echo.private(reverbChannel);
        reverbStream.listen(`.${reverbCreatedEvent}`, (payload) => {
            emitRealtimeEvent('dlds:event-created', payload);
        });
        reverbStream.listen(`.${reverbUpdatedEvent}`, (payload) => {
            emitRealtimeEvent('dlds:event-updated', payload);
        });
    } catch (error) {
        updateRealtimeState({
            enabled: false,
            connected: false,
            status: 'error',
            lastError: error,
        });
        window.dldsMarkDataSyncFailure('realtime-init');
        console.error('[DLDS] Failed to initialize Reverb/Echo. Polling fallback remains active:', error);
    }
} else {
    updateRealtimeState({
        enabled: false,
        connected: false,
        status: 'disabled',
    });
    console.warn('[DLDS] Reverb disabled: VITE_REVERB_APP_KEY is empty. Polling fallback only.');
}

let autoRefreshEnabled = localStorage.getItem('dlds_auto_refresh') === 'true';
let autoRefreshTimer = null;

const syncAutoRefreshUI = () => {
    const btn = document.getElementById('toggle-auto-refresh');
    if (btn) {
        btn.textContent = autoRefreshEnabled ? 'Live Sync: ON' : 'Live Sync: OFF';
        btn.classList.toggle('active', autoRefreshEnabled);
    }
    
    setLivePillState(window.dldsRealtime);
};

const triggerAutoRefresh = () => {
    if (!document.hidden) {
        window.dispatchEvent(new CustomEvent('dlds:auto-refresh'));
    }
};

const startAutoRefreshLoop = () => {
    if (autoRefreshTimer) {
        window.clearInterval(autoRefreshTimer);
    }
    autoRefreshTimer = window.setInterval(triggerAutoRefresh, 4000);
};

const stopAutoRefreshLoop = () => {
    if (autoRefreshTimer) {
        window.clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
};

window.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('toggle-auto-refresh');
    if (btn) {
        btn.addEventListener('click', () => {
            autoRefreshEnabled = !autoRefreshEnabled;
            localStorage.setItem('dlds_auto_refresh', autoRefreshEnabled ? 'true' : 'false');
            syncAutoRefreshUI();
            
            if (autoRefreshEnabled) {
                startAutoRefreshLoop();
                triggerAutoRefresh();
            } else {
                stopAutoRefreshLoop();
            }
        });
    }

    syncAutoRefreshUI();
    if (autoRefreshEnabled) {
        startAutoRefreshLoop();
    }
});


window.dldsEscapeHtml = (value) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
};

window.dldsFormatTime = (value) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString();
};

window.dldsClipText = (value, max = 120) => {
    const text = String(value ?? '');
    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, max - 1)}…`;
};

window.dldsRenderFilterChips = ({
    container,
    state,
    defaults,
    labels = {},
    hiddenKeys = ['page'],
}) => {
    if (!container) {
        return;
    }

    const entries = Object.entries(state).filter(([key, value]) => {
        if (hiddenKeys.includes(key)) {
            return false;
        }

        const normalized = String(value ?? '').trim();
        return normalized !== '' && normalized !== String(defaults[key] ?? '');
    });

    if (entries.length === 0) {
        container.innerHTML = '<span class="no-active-filters">No active filters</span>';
        return;
    }

    container.innerHTML = entries.map(([key, value]) => {
        const label = labels[key] ?? key.replaceAll('_', ' ');
        return `<span class="active-filter-chip"><strong>${window.dldsEscapeHtml(label)}:</strong> ${window.dldsEscapeHtml(value)}</span>`;
    }).join('');
};

window.dldsSeverityClass = (value) => {
    const normalized = String(value ?? 'LOW').toLowerCase();

    if (normalized === 'critical' || normalized === 'high') {
        return 'severity-high';
    }

    if (normalized === 'medium') {
        return 'severity-medium';
    }

    return 'severity-low';
};

window.dldsCleanQuery = (state, defaults = {}) => {
    const query = new URLSearchParams();

    Object.entries(state).forEach(([key, value]) => {
        const normalized = String(value ?? '').trim();
        const defaultValue = String(defaults[key] ?? '').trim();

        if (normalized === '' || normalized === defaultValue) {
            return;
        }

        query.set(key, normalized);
    });

    return query;
};

window.dldsEndpointWithQuery = (endpoint, query) => {
    const serialized = query.toString();

    return serialized === '' ? endpoint : `${endpoint}?${serialized}`;
};

window.dldsFetchJson = async (url, options = {}, timeoutMs = 10_000) => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options,
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return await response.json();
    } finally {
        window.clearTimeout(timeout);
    }
};

window.startDldsPolling = ({
    endpoint,
    intervalMs = 5000,
    connectedIntervalMs = 30000,
    onData,
    onError = null,
}) => {
    let active = true;
    let timer = null;
    let inFlightController = null;

    const scheduleNext = () => {
        if (!active) {
            return;
        }

        const delay = window.dldsHasRealtimeConnection()
            ? Math.max(intervalMs, connectedIntervalMs)
            : intervalMs;

        timer = window.setTimeout(run, delay);
    };

    const run = async () => {
        if (!active || inFlightController) {
            scheduleNext();
            return;
        }

        inFlightController = new AbortController();
        const timeout = window.setTimeout(() => inFlightController.abort(), 10_000);

        try {
            const response = await fetch(endpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: inFlightController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            onData(payload);
            window.dldsMarkDataSync('poll');
        } catch (error) {
            if (error?.name !== 'AbortError') {
                window.dldsMarkDataSyncFailure('poll');
                console.error(`Live refresh failed for ${endpoint}:`, error);
                if (typeof onError === 'function') {
                    onError(error);
                }
            }
        } finally {
            window.clearTimeout(timeout);
            inFlightController = null;
            scheduleNext();
        }
    };

    run();

    return () => {
        active = false;

        if (timer) {
            window.clearTimeout(timer);
        }

        if (inFlightController) {
            inFlightController.abort();
        }
    };
};

const initializeDldsUi = () => {
    if (typeof window.particlesJS === 'function') {
        window.particlesJS('particles-js', {
            particles: {
                number: { value: 120 },
                color: { value: '#00ff9f' },
                shape: { type: 'circle' },
                opacity: { value: 0.4 },
                size: { value: 2 },
                move: {
                    enable: true,
                    speed: 1.5,
                    direction: 'none',
                    out_mode: 'out',
                },
                line_linked: {
                    enable: true,
                    distance: 120,
                    color: '#00ff9f',
                    opacity: 0.2,
                    width: 1,
                },
            },
            interactivity: {
                events: {
                    onhover: { enable: true, mode: 'repulse' },
                },
            },
        });
    }

    const mobileMedia = window.matchMedia('(max-width: 639px)');
    const tabletMedia = window.matchMedia('(min-width: 640px) and (max-width: 1024px)');

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('toggleBtn');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (typeof window.dldsInitializeSidebar === 'function') {
        window.dldsInitializeSidebar();
    } else if (sidebar && sidebarToggle) {
        const syncSidebarState = () => {
            const expanded = sidebar.classList.contains('expanded');
            const collapsed = sidebar.classList.contains('collapsed');

            document.body.classList.remove('sidebar-open', 'sidebar-expanded', 'sidebar-collapsed');

            if (mobileMedia.matches) {
                if (document.body.dataset.sidebarOpen === '1') {
                    document.body.classList.add('sidebar-open');
                }
            } else if (expanded && !collapsed) {
                document.body.classList.add('sidebar-expanded');
            } else if (collapsed && !expanded) {
                document.body.classList.add('sidebar-collapsed');
            }

            sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            mobileSidebarToggle?.setAttribute('aria-expanded', document.body.dataset.sidebarOpen === '1' ? 'true' : 'false');
        };

        const setSidebarExpanded = (expanded) => {
            if (mobileMedia.matches) {
                sidebar.classList.toggle('expanded', expanded);
                sidebar.classList.toggle('collapsed', !expanded);
                document.body.dataset.sidebarOpen = expanded ? '1' : '0';
                syncSidebarState();
                return;
            }

            sidebar.classList.toggle('expanded', expanded);
            sidebar.classList.toggle('collapsed', !expanded);
            document.body.dataset.sidebarOpen = '0';
            syncSidebarState();
        };

        const openSidebar = () => setSidebarExpanded(true);
        const closeSidebar = () => setSidebarExpanded(false);

        const toggleSidebar = () => {
            if (mobileMedia.matches) {
                setSidebarExpanded(document.body.dataset.sidebarOpen !== '1');
                return;
            }

            setSidebarExpanded(!sidebar.classList.contains('expanded'));
        };

        sidebarToggle.addEventListener('click', toggleSidebar);
        mobileSidebarToggle?.addEventListener('click', openSidebar);
        sidebarBackdrop?.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileMedia.matches) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        const syncOnResize = () => {
            if (mobileMedia.matches) {
                setSidebarExpanded(false);
                return;
            }

            if (tabletMedia.matches) {
                document.body.dataset.sidebarOpen = '0';
                setSidebarExpanded(false);
                return;
            } else {
                document.body.dataset.sidebarOpen = '0';
                setSidebarExpanded(true);
                return;
            }
        };

        mobileMedia.addEventListener('change', syncOnResize);
        tabletMedia.addEventListener('change', syncOnResize);
        syncOnResize();
        window.dldsSyncSidebar = syncSidebarState;
    }

    setLivePillState(window.dldsRealtime);
    window.addEventListener('dlds:realtime-state', (event) => {
        setLivePillState(event.detail);
    });
    window.addEventListener('dlds:data-sync', () => {
        setLivePillState(window.dldsRealtime);
    });
    window.addEventListener('dlds:data-sync-failure', () => {
        setLivePillState(window.dldsRealtime);
    });
    window.setInterval(() => {
        setLivePillState(window.dldsRealtime);
    }, 1000);

    document.querySelectorAll('form.table-controls').forEach((form, index) => {
        const parent = form.parentElement;
        if (!parent) {
            return;
        }

        if (!form.id) {
            form.id = `dlds-filters-${index + 1}`;
        }

        const existingToggle = parent.querySelector(`[data-filters-toggle-for="${form.id}"]`);
        if (existingToggle) {
            return;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'button-ghost filters-toggle';
        toggle.dataset.filtersToggleFor = form.id;
        toggle.setAttribute('aria-controls', form.id);
        toggle.innerHTML = '<span>Filters</span><span class="filters-toggle-state">Show</span>';

        const stateLabel = toggle.querySelector('.filters-toggle-state');
        const syncFilterPanelState = ({ forceCollapse = false } = {}) => {
            if (!mobileMedia.matches) {
                form.classList.remove('is-collapsed');
                toggle.setAttribute('aria-expanded', 'true');
                if (stateLabel) {
                    stateLabel.textContent = 'Hide';
                }
                return;
            }

            if (forceCollapse) {
                form.classList.add('is-collapsed');
            }

            const collapsed = form.classList.contains('is-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (stateLabel) {
                stateLabel.textContent = collapsed ? 'Show' : 'Hide';
            }
        };

        toggle.addEventListener('click', () => {
            form.classList.toggle('is-collapsed');
            syncFilterPanelState();
        });

        parent.insertBefore(toggle, form);
        syncFilterPanelState({ forceCollapse: true });
        mobileMedia.addEventListener('change', () => {
            syncFilterPanelState({ forceCollapse: mobileMedia.matches });
        });
    });

};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDldsUi, { once: true });
} else {
    initializeDldsUi();
}
