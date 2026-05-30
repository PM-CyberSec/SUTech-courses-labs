<aside id="sidebar" class="sidebar expanded" aria-label="Primary navigation">
    <div class="sidebar-header">
        <div class="brand-lockup">
            <h1 class="brand-title">DLDS SOC</h1>
            <p class="brand-subtitle">Monitoring Console</p>
        </div>
        <button id="toggleBtn" type="button" aria-label="Toggle sidebar" aria-expanded="true" aria-controls="sidebar">☰</button>
    </div>

    <nav class="sidebar-links">
        <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
            <span class="nav-icon">DB</span>
            <span class="text">Dashboard</span>
        </a>
        <a href="{{ route('events.index') }}" @class(['active' => request()->routeIs('events.index')])>
            <span class="nav-icon">EV</span>
            <span class="text">Events</span>
        </a>
        <a href="{{ route('alerts.index') }}" @class(['active' => request()->routeIs('alerts.index')])>
            <span class="nav-icon">AL</span>
            <span class="text">Alerts</span>
        </a>
        <a href="{{ route('network.index') }}" @class(['active' => request()->routeIs('network.index')])>
            <span class="nav-icon">NW</span>
            <span class="text">Network</span>
        </a>
        <a href="{{ route('processes.index') }}" @class(['active' => request()->routeIs('processes.index')])>
            <span class="nav-icon">PR</span>
            <span class="text">Processes</span>
        </a>
    </nav>
</aside>