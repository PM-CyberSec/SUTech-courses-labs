<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DLDS SOC Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/sidebar.js', 'resources/js/app.js'])
</head>
<body>
<div id="particles-js"></div>
<a href="#main-content" class="skip-link">Skip to main content</a>
<div class="layout">
    @include('layouts.sidebar')

    <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

    <main id="main-content" class="content">
        <div class="content-inner">
            @include('layouts.navbar')

            @if (session('status'))
                <p class="status-banner" role="status" aria-live="polite">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="status-banner status-banner-error" role="alert">{{ $errors->first() }}</p>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
