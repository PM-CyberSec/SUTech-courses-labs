<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DLDS SOC Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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

<script src="https://cdn.jsdelivr.net/npm/particles.js"></script>
<script>
if (typeof particlesJS !== 'undefined') {
    particlesJS("particles-js", {
        particles: {
            number: { value: 120 },
            color: { value: "#00ff9f" },
            shape: { type: "circle" },
            opacity: { value: 0.4 },
            size: { value: 2 },
            move: {
                enable: true,
                speed: 1.5,
                direction: "none",
                out_mode: "out"
            },
            line_linked: {
                enable: true,
                distance: 120,
                color: "#00ff9f",
                opacity: 0.2,
                width: 1
            }
        },
        interactivity: {
            events: {
                onhover: { enable: true, mode: "repulse" }
            }
        }
    });
}
</script>
</body>
</html>