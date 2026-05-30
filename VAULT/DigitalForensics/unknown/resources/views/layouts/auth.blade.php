<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DLDS Authentication')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
<main class="auth-shell" aria-labelledby="auth-page-title">
    <section class="auth-intro" aria-hidden="true">
        <p class="auth-kicker">Digital Leak Detection System</p>
        <h1 id="auth-page-title" class="auth-title">Real-time SOC visibility built for fast decisions.</h1>
        <p class="auth-copy">Track high-risk events, monitor network activity, and react quickly with a live incident timeline.</p>
        <ul class="auth-list">
            <li>Realtime events and alerts streamed to the dashboard</li>
            <li>Severity-focused views for rapid threat triage</li>
            <li>Unified telemetry from detection engine pipelines</li>
        </ul>
    </section>

    <section class="auth-panel">
        @if (session('status'))
            <p class="auth-status" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <p class="auth-status auth-status-error" role="alert">{{ $errors->first() }}</p>
        @endif

        @yield('content')
    </section>
</main>
</body>
</html>
