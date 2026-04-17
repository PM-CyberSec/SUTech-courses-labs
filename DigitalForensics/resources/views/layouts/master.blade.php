<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLDS SOC Dashboard</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="particles-js"></div>
<div class="layout">

    <div id="sidebar" class="sidebar expanded">
        <div class="sidebar-header">
            <h3>DLDS</h3>
            <button id="toggleBtn">☰</button>
        </div>
        <a href="/"><span class="text">Dashboard</span></a>
<a href="/events"><span class="text">Events</span></a>
<a href="/alerts"><span class="text">Alerts</span></a>
<a href="/network"><span class="text">Network</span></a>
<a href="/processes"><span class="text">Processes</span></a>
    </div>

    <div id="content" class="content">
        @yield('content')
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/particles.js"></script>

<script>
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
</script>
</script>
</body>
</html>
