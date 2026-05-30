@extends('layouts.master')

@section('title', 'Event Details | DLDS SOC')

@section('content')
<section class="page-header">
    <div>
        <h2 class="page-title" id="event-title">Event #{{ $event->id }} Details</h2>
        <p class="page-description">Deep dive into event telemetry and AI classification.</p>
    </div>
    <div class="page-meta">
        <span id="event-details-sync" class="pagination-info">Waiting for sync</span>
        <a class="button-secondary" href="{{ route('events.index') }}">← Back to Events</a>
    </div>
</section>

<div class="layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; min-height: unset; margin-top: 20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Event Telemetry</h3>
        </div>
        <table class="table-details">
            <tbody>
                <tr>
                    <th scope="row">Timestamp</th>
                    <td id="event-timestamp" class="mono">{{ $event->event_time?->format('Y-m-d H:i:s') ?? '-' }}</td>
                </tr>
                <tr>
                    <th scope="row">Type</th>
                    <td><span id="event-type" class="type-pill" data-type="{{ strtolower($event->type ?? 'event') }}">{{ strtoupper($event->type ?? 'EVENT') }}</span></td>
                </tr>
                <tr>
                    <th scope="row">Severity</th>
                    <td><span id="event-severity" class="severity-{{ strtolower($event->severity ?? 'low') }}">{{ $event->severity ?? 'LOW' }}</span></td>
                </tr>
                <tr>
                    <th scope="row">Process</th>
                    <td id="event-process"><span class="mono">PID {{ $event->pid }}</span> | {{ $event->process_name ?? '-' }}</td>
                </tr>
                <tr>
                    <th scope="row">Source</th>
                    <td id="event-source" class="mono">{{ $event->src_ip ?? '-' }}:{{ $event->src_port ?? 0 }}</td>
                </tr>
                <tr>
                    <th scope="row">Destination</th>
                    <td id="event-destination" class="mono">{{ $event->dst_ip ?? '-' }}:{{ $event->dst_port ?? 0 }}</td>
                </tr>
                <tr>
                    <th scope="row">Bytes Sent</th>
                    <td id="event-bytes" class="mono">{{ number_format($event->bytes_sent ?? 0) }}</td>
                </tr>
                <tr>
                    <th scope="row">Alert / Description</th>
                    <td><span id="event-alert-type">{{ $event->alert_type ?? '-' }}</span> <br> <span id="event-description" class="muted">{{ $event->description ?? '-' }}</span></td>
                </tr>
                <tr>
                    <th scope="row">Event Hash</th>
                    <td id="event-hash" class="mono" style="font-size: 0.75rem; word-break: break-all;">{{ $event->event_hash }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="event-ai-card" class="card" style="border-top: 4px solid {{ $event->ai_label === 'malicious' ? 'var(--danger)' : ($event->ai_label === 'suspicious' ? 'var(--warning)' : 'var(--success)') }}">
        <div class="card-header">
            <h3 class="card-title">AI Forensic Analysis</h3>
        </div>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Classification</div>
                    <span id="ai-label" class="type-pill" data-type="{{ $event->ai_label ?? 'benign' }}" style="font-size: 1rem; padding: 6px 14px; margin-top: 5px;">{{ strtoupper($event->ai_label ?? 'BENIGN') }}</span>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Confidence</div>
                    <div id="confidence" class="mono" style="font-size: 1.5rem; color: var(--text-primary);">{{ number_format(($event->confidence ?? 0) * 100, 1) }}%</div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Anomaly Score</div>
                    <div id="anomaly" class="mono" style="font-size: 1.2rem; color: {{ ($event->anomaly_score ?? 0) > 0.5 ? 'var(--danger)' : 'var(--success)' }};">{{ number_format($event->anomaly_score ?? 0, 2) }}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Model Version</div>
                    <div id="model-version" class="mono" style="font-size: 1rem;">{{ $event->model_version ?? 'N/A' }}</div>
                </div>
            </div>

            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">AI Explanation</div>
                <p id="ai-reason" style="color: var(--text-secondary); line-height: 1.6;">{{ $event->ai_reason ?? 'No heuristic explanation provided.' }}</p>
            </div>

            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">Correlated Evidence</div>
                <ul id="ai-evidence" style="color: var(--text-secondary); padding-left: 20px; margin: 0;">
                    @forelse(($event->ai_evidence ?? []) as $evidence)
                        <li>{{ $evidence }}</li>
                    @empty
                        <li>No correlated evidence provided.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title">Raw Logs Inspection</h3>
    </div>
    <div style="padding: 16px;">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px;">Security notice: Raw logs are sanitized and represent reconstructed untrusted inputs.</p>
        <pre id="raw-event-json" class="mono" style="background: var(--bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border); overflow-x: auto; color: #a5d6ff;">{{ json_encode($event->toArray(), JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>

<style>
.table-details {
    width: 100%;
    border-collapse: collapse;
}
.table-details th {
    width: 30%;
    padding: 12px 20px;
    background: transparent;
    border-bottom: 1px solid var(--border);
    color: var(--text-muted);
}
.table-details td {
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
}
</style>

<script type="module" nonce="{{ $cspNonce ?? '' }}">
(() => {
    const eventId = {{ (int) $event->id }};
    const endpoint = `/api/dlds/public/events/${eventId}`;
    const syncLabel = document.getElementById('event-details-sync');
    const rawEventJson = document.getElementById('raw-event-json');
    const aiCard = document.getElementById('event-ai-card');
    let pollingTimer = null;

    const byId = (id) => document.getElementById(id);
    const safeText = (value, fallback = '-') => {
        const text = String(value ?? '').trim();
        return text === '' ? fallback : text;
    };
    const formatPercent = (value) => `${(Number(value ?? 0) * 100).toFixed(1)}%`;
    const formatScore = (value) => Number(value ?? 0).toFixed(2);
    const accentForLabel = (label) => {
        const normalized = String(label ?? 'benign').toLowerCase();
        if (normalized === 'malicious') return 'var(--danger)';
        if (normalized === 'suspicious') return 'var(--warning)';
        return 'var(--success)';
    };

    const setSyncStatus = (message) => {
        if (syncLabel) {
            syncLabel.textContent = message;
        }
    };

    const renderEvidence = (items) => {
        const list = byId('ai-evidence');
        if (!list) return;

        const evidence = Array.isArray(items)
            ? items.filter((item) => String(item ?? '').trim() !== '')
            : [];

        if (evidence.length === 0) {
            list.innerHTML = '<li>No correlated evidence provided.</li>';
            return;
        }

        list.innerHTML = evidence
            .map((item) => `<li>${window.dldsEscapeHtml(item)}</li>`)
            .join('');
    };

    const updateEventDetails = (event) => {
        if (!event || Number(event.id) !== eventId) {
            return;
        }

        byId('event-title').textContent = `Event #${event.id} Details`;
        byId('event-timestamp').textContent = window.dldsFormatTime(event.event_time ?? event.timestamp);

        const typeEl = byId('event-type');
        typeEl.textContent = safeText(String(event.type ?? 'event').toUpperCase(), 'EVENT');
        typeEl.dataset.type = String(event.type ?? 'event').toLowerCase();

        const severity = safeText(event.severity, 'LOW').toUpperCase();
        const severityEl = byId('event-severity');
        severityEl.textContent = severity;
        severityEl.className = window.dldsSeverityClass(severity);

        byId('event-process').innerHTML = `<span class="mono">PID ${Number(event.pid ?? 0)}</span> | ${window.dldsEscapeHtml(safeText(event.process_name))}`;
        byId('event-source').textContent = `${safeText(event.src_ip)}:${Number(event.src_port ?? 0)}`;
        byId('event-destination').textContent = `${safeText(event.dst_ip)}:${Number(event.dst_port ?? 0)}`;
        byId('event-bytes').textContent = Number(event.bytes_sent ?? 0).toLocaleString();
        byId('event-alert-type').textContent = safeText(event.alert_type);
        byId('event-description').textContent = safeText(event.description);
        byId('event-hash').textContent = safeText(event.event_hash);

        const aiLabel = safeText(event.ai_label, 'benign').toLowerCase();
        const aiLabelEl = byId('ai-label');
        aiLabelEl.textContent = aiLabel.toUpperCase();
        aiLabelEl.dataset.type = aiLabel;

        byId('confidence').textContent = formatPercent(event.confidence);

        const anomalyEl = byId('anomaly');
        anomalyEl.textContent = formatScore(event.anomaly_score);
        anomalyEl.style.color = Number(event.anomaly_score ?? 0) > 0.5 ? 'var(--danger)' : 'var(--success)';

        byId('model-version').textContent = safeText(event.model_version, 'N/A');
        byId('ai-reason').textContent = safeText(event.ai_reason, 'No heuristic explanation provided.');

        renderEvidence(event.ai_evidence);

        if (aiCard) {
            aiCard.style.borderTop = `4px solid ${accentForLabel(aiLabel)}`;
        }

        if (rawEventJson) {
            rawEventJson.textContent = JSON.stringify(event, null, 2);
        }

        window.dldsMarkDataSync('event-details');
    };

    const fetchEvent = async (reason = 'Polling sync') => {
        try {
            const payload = await window.dldsFetchJson(endpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }, 8000);

            updateEventDetails(payload);
            setSyncStatus(`${reason}: ${new Date().toLocaleTimeString()}`);
        } catch (error) {
            window.dldsMarkDataSyncFailure('event-details');
            setSyncStatus('Sync retrying');
            console.error('Failed to refresh event details:', error);
        }
    };

    const startPolling = () => {
        if (pollingTimer !== null) {
            return;
        }

        pollingTimer = window.setInterval(() => {
            fetchEvent('Polling sync');
        }, 5000);
    };

    const stopPolling = () => {
        if (pollingTimer === null) {
            return;
        }

        window.clearInterval(pollingTimer);
        pollingTimer = null;
    };

    window.dldsSubscribeToEventStream({
        onCreated: (event) => {
            if (Number(event?.id ?? 0) === eventId) {
                updateEventDetails(event);
                setSyncStatus(`Realtime sync: ${new Date().toLocaleTimeString()}`);
            }
        },
        onUpdated: (event) => {
            if (Number(event?.id ?? 0) === eventId) {
                updateEventDetails(event);
                setSyncStatus(`Realtime sync: ${new Date().toLocaleTimeString()}`);
            }
        },
    });

    window.addEventListener('dlds:realtime-state', (domEvent) => {
        const state = domEvent.detail ?? {};
        if (state.connected) {
            stopPolling();
            setSyncStatus('CONNECTED');
            return;
        }

        setSyncStatus(state.status === 'connecting' ? 'CONNECTING' : 'POLLING');
        startPolling();
    });

    const initialRealtimeState = window.dldsRealtime ?? {};
    if (initialRealtimeState.connected) {
        setSyncStatus('CONNECTED');
    } else if (initialRealtimeState.status === 'connecting') {
        setSyncStatus('CONNECTING');
    } else {
        setSyncStatus('POLLING');
    }

    fetchEvent('Initial sync');

    if (!window.dldsHasRealtimeConnection()) {
        startPolling();
    }
})();
</script>
@endsection
