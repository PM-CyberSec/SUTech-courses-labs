<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'AutoConfigLab' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-link: #cbd5e1;
            --sidebar-link-active: #ffffff;
            --sidebar-hover: #1e293b;
            --body-bg: #f1f5f9;
            --card-border: #e2e8f0;
        }
        body { background: var(--body-bg); }
        .app-shell { min-height: 100vh; }
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: #fff;
            position: fixed;
            inset: 0 auto 0 0;
            padding: 20px 14px;
            overflow-y: auto;
        }
        .sidebar .brand {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 24px;
            letter-spacing: .2px;
        }
        .sidebar .nav-link {
            color: var(--sidebar-link);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 4px;
        }
        .sidebar .nav-link:hover { background: var(--sidebar-hover); color: var(--sidebar-link-active); }
        .sidebar .nav-link.active { background: #2563eb; color: #fff; }
        .main-panel {
            margin-left: 250px;
            min-height: 100vh;
            padding: 20px 20px 30px;
        }
        .topbar {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .content-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 16px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 14px;
        }
        .table thead th { white-space: nowrap; }
        @media (max-width: 991.98px) {
            .sidebar { position: static; width: 100%; border-radius: 0 0 14px 14px; }
            .main-panel { margin-left: 0; padding-top: 8px; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">AutoConfigLab</div>
        <nav class="nav flex-column mb-3">
            <a class="nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="nav-link {{ request()->routeIs('devices*') ? 'active' : '' }}" href="{{ route('devices.index') }}">Devices</a>
            <a class="nav-link {{ request()->routeIs('inventories*') ? 'active' : '' }}" href="{{ route('inventories.index') }}">Inventories</a>
            <a class="nav-link {{ request()->routeIs('templates*') ? 'active' : '' }}" href="{{ route('templates.index') }}">Templates</a>
            <a class="nav-link {{ request()->routeIs('deployments*') ? 'active' : '' }}" href="{{ route('deployments.index') }}">Deployments</a>
            <a class="nav-link {{ request()->routeIs('topologies*') ? 'active' : '' }}" href="{{ route('topologies.index') }}">Topologies</a>
            @if(in_array($currentRole ?? session('role', 'viewer'), ['admin', 'engineer']))
                <a class="nav-link {{ request()->routeIs('ai-topology*') ? 'active' : '' }}" href="{{ route('ai-topology.index') }}">AI Topology</a>
            @endif
            <a class="nav-link {{ request()->routeIs('logs*') ? 'active' : '' }}" href="{{ route('logs.index') }}">Logs</a>
        </nav>
        <div class="small text-secondary-emphasis mb-2">Role Switch (Local)</div>
        <div class="d-flex gap-1 flex-wrap">
            <a class="btn btn-sm btn-outline-light" href="{{ route('role.switch', 'admin') }}">Admin</a>
            <a class="btn btn-sm btn-outline-light" href="{{ route('role.switch', 'engineer') }}">Engineer</a>
            <a class="btn btn-sm btn-outline-light" href="{{ route('role.switch', 'viewer') }}">Viewer</a>
        </div>
    </aside>

    <main class="main-panel">
        <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0">{{ $title ?? 'AutoConfigLab' }}</h6>
                <small class="text-muted">{{ $subtitle ?? 'Network Automation Control Plane' }}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-primary">Role: {{ ucfirst($currentRole ?? session('role', 'viewer')) }}</span>
                <span class="badge text-bg-secondary">{{ now()->format('Y-m-d H:i') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @php
            $errorList = [];
            if (isset($errors)) {
                if ($errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag) {
                    $errorList = $errors->all();
                } elseif (is_array($errors)) {
                    $errorList = array_values(array_filter($errors, fn ($item) => is_string($item) && $item !== ''));
                }
            }
        @endphp
        @if(!empty($errorList))
            <div class="alert alert-danger mb-3">
                <div class="fw-semibold mb-1">Validation errors:</div>
                <ul class="mb-0">
                    @foreach($errorList as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script>
document.querySelectorAll('form.js-confirm').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const message = form.getAttribute('data-confirm') || 'Are you sure?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});

document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
        const button = form.querySelector('button[type="submit"]');
        if (button && !button.dataset.noLoading) {
            button.disabled = true;
            const label = button.innerHTML;
            button.dataset.originalLabel = label;
            button.innerHTML = 'Processing...';
            setTimeout(function() {
                button.disabled = false;
                button.innerHTML = button.dataset.originalLabel || label;
            }, 5000);
        }
    });
});
</script>
</body>
</html>
