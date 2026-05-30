<header class="topbar">
    <div class="topbar-title-wrap">
        <p class="topbar-title">Security Operations Dashboard</p>
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

        <button id="toggle-auto-refresh" class="button-ghost" style="margin-right: 10px;">Live Sync: OFF</button>
        <span id="realtime-pill" class="live-pill" data-state="connecting" role="status" aria-live="polite">
            Connecting live feed
        </span>
        @guest
            <a href="{{ route('login') }}">Login</a>
            <a href="{{ route('register') }}">Sign Up</a>
        @else
            <span class="welcome">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button-link">Logout</button>
            </form>
        @endguest
    </div>
</header>