import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const reverbForceTls = reverbScheme === 'https';
const reverbChannel = 'dlds-events';
const reverbEvent = 'dlds.event.created';

window.dldsRealtime = {
    enabled: false,
    connected: false,
    channel: reverbChannel,
    event: reverbEvent,
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

const livePillText = (state) => {
    if (localStorage.getItem('dlds_auto_refresh') === 'true') {
        return 'Connecting live feed';
    }

    if (!state?.enabled || state?.status === 'disabled') {
        return 'Realtime disabled';
    }

    if (state?.status === 'connected') {
        return 'Live feed connected';
    }

    if (state?.status === 'error') {
        return 'Connection issue';
    }

    if (state?.status === 'disconnected') {
        return 'Live feed offline';
    }

    return 'Connecting live feed';
};

const setLivePillState = (state) => {
    const pill = document.getElementById('realtime-pill');
    if (!pill) {
        return;
    }

    if (localStorage.getItem('dlds_auto_refresh') === 'true') {
        pill.dataset.state = 'connecting';
    } else {
        pill.dataset.state = state?.status ?? 'connecting';
    }
    pill.textContent = livePillText(state);
};

const emitRealtimeCreatedEvent = (payload) => {
    const eventPayload = payload?.event && typeof payload.event === 'object'
        ? payload.event
        : null;

    if (!eventPayload) {
        console.warn('Received realtime payload without an event object:', payload);
        return;
    }

    window.dispatchEvent(new CustomEvent('dlds:event-created', {
        detail: {
            event: eventPayload,
            raw: payload,
            received_at: new Date().toISOString(),
        },
    }));
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
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbForceTls,
            enabledTransports: ['ws', 'wss'],
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
                console.error('[DLDS] Reverb connection error:', error);
            });
        }

        window.Echo.channel(reverbChannel).listen(`.${reverbEvent}`, (payload) => {
            emitRealtimeCreatedEvent(payload);
        });
    } catch (error) {
        updateRealtimeState({
            enabled: false,
            connected: false,
            status: 'error',
            lastError: error,
        });
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

    if (autoRefreshEnabled && !window.dldsRealtime.connected) {
        updateRealtimeState({
            enabled: true,
            status: 'connected',
        });
    } else if (!autoRefreshEnabled && !window.dldsRealtime.connected) {
        updateRealtimeState({
            enabled: false,
            status: 'disabled',
        });
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
        } catch (error) {
            if (error?.name !== 'AbortError') {
                console.error(`Live refresh failed for ${endpoint}:`, error);
                if (typeof onError === 'function') {
                    onError(error);
                }
            }
        } finally {
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

document.addEventListener('DOMContentLoaded', () => {
    setLivePillState(window.dldsRealtime);
    window.addEventListener('dlds:realtime-state', (event) => {
        setLivePillState(event.detail);
    });

    const sidebar = document.getElementById('sidebar');
    const button = document.getElementById('toggleBtn');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !button) {
        return;
    }

    const mobileMedia = window.matchMedia('(max-width: 1024px)');

    const syncToggleState = () => {
        const expanded = sidebar.classList.contains('expanded');
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const closeMobileSidebar = () => {
        if (!mobileMedia.matches) {
            return;
        }

        sidebar.classList.remove('expanded');
        sidebar.classList.add('collapsed');
        document.body.classList.remove('sidebar-open');
        syncToggleState();
    };

    const openMobileSidebar = () => {
        sidebar.classList.add('expanded');
        sidebar.classList.remove('collapsed');
        document.body.classList.add('sidebar-open');
        syncToggleState();
    };

    const toggleSidebar = () => {
        if (mobileMedia.matches) {
            if (document.body.classList.contains('sidebar-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
            return;
        }

        sidebar.classList.toggle('expanded');
        sidebar.classList.toggle('collapsed');
        document.body.classList.remove('sidebar-open');
        syncToggleState();
    };

    button.addEventListener('click', toggleSidebar);
    backdrop?.addEventListener('click', closeMobileSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileMedia.matches) {
                closeMobileSidebar();
            }
        });
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    const syncOnResize = () => {
        if (!mobileMedia.matches) {
            document.body.classList.remove('sidebar-open');
            if (!sidebar.classList.contains('expanded') && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
            }
        } else {
            sidebar.classList.remove('expanded');
            sidebar.classList.add('collapsed');
            document.body.classList.remove('sidebar-open');
        }
        syncToggleState();
    };

    mobileMedia.addEventListener('change', syncOnResize);
    syncOnResize();
});
