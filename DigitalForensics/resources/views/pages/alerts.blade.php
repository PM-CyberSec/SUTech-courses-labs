<!-- @extends('layouts.master')

@section('content')

<h2>Alerts</h2>

<div class="card">

<table id="alertsTable">
    <thead>
        <tr>
            <th>ID</th>
            <th>Severity</th>
            <th>Type</th>
            <th>Description</th>
        </tr>
    </thead>

    <tbody>
    @forelse($alerts as $alert)
        <tr>
            <td>{{ $alert->id }}</td>
            <td>{{ $alert->severity }}</td>
            <td>{{ $alert->alert_type }}</td>
            <td>{{ $alert->description }}</td>
        </tr>
    @empty
        <tr><td colspan="4">No alerts</td></tr>
    @endforelse
    </tbody>
</table>
<script type="module">
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: "dlds-key",
    wsHost: window.location.hostname,
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ["ws"],
});

window.Echo.channel("dlds-alerts")
    .listen(".new.alert", (e) => {
        console.log("NEW ALERT:", e);

        const table = document.getElementById("alertsTable");

        const row = `
            <tr>
                <td>${e.event.id}</td>
                <td>${e.event.severity}</td>
                <td>${e.event.alert_type ?? ''}</td>
                <td>${e.event.description ?? ''}</td>
            </tr>
        `;

        table.insertAdjacentHTML("afterbegin", row);
    });
</script>

</div>

@endsection -->

@extends('layouts.master')

@section('content')

<h2>Live Alerts</h2>

<div id="alerts"></div>

<script>
async function loadAlerts() {
    const res = await fetch('/api/dlds/alerts');
    const data = await res.json();

    let html = `
        <table border="1" width="100%" cellpadding="5">
            <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Severity</th>
                <th>Description</th>
            </tr>
    `;

    data.forEach(e => {
        html += `
            <tr>
                <td>${e.timestamp}</td>
                <td>${e.type}</td>
                <td>${e.severity}</td>
                <td>${e.description}</td>
            </tr>
        `;
    });

    html += "</table>";

    document.getElementById("alerts").innerHTML = html;
}

loadAlerts();
setInterval(loadAlerts, 2000);
</script>

@endsection