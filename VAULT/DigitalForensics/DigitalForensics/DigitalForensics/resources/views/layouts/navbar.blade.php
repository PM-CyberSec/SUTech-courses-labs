<header class="topbar">
    <div class="topbar-title-wrap">
        <div class="topbar-title-row">
            <button id="mobile-sidebar-toggle" type="button" class="button-ghost mobile-only" aria-label="Open navigation menu" aria-controls="sidebar">
                ☰
            </button>
            <p class="topbar-title">Security Operations Dashboard</p>
        </div>
        <p class="topbar-subtitle">Live threat telemetry with realtime event streaming</p>
    </div>

    <div class="topbar-actions">
        <form class="topbar-search" method="GET" action="{{ route('events.index') }}">
            <label class="visually-hidden" for="global-search">Search events</label>
            <input
                id="global-search"
                type="search"
                name="search"
                value="{{ request()->query('search', '') }}"
                placeholder="Search by type, severity, IP, process..."
            >
            <button class="button-ghost" type="submit">Search</button>
        </form>

        <div class="topbar-live-group">
            <button id="toggle-auto-refresh" class="button-ghost">Live Sync: OFF</button>
            <span id="realtime-last-sync" class="live-sync-time" aria-live="polite">Last sync: --:--:--</span>
            <span id="realtime-pill" class="live-pill" data-state="connecting" role="status" aria-live="polite">
                CONNECTING
            </span>
        </div>

        <div class="topbar-user-group">
            @guest
                <a href="{{ route('login') }}">Login</a>
                @if(config('app.public_registration') || !app()->environment('production'))
                    <a href="{{ route('register') }}">Sign Up</a>
                @endif
            @else
                <span class="welcome">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button-link">Logout</button>
                </form>
            @endguest
        </div>
    </div>
</header>
