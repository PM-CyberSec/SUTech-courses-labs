<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>WebSecService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/future.css') }}">
</head>
<body class="has-fixed-navbar">
     @include('layouts.menu')

     @yield('content')

<div class="purple-dot" title="Cyber Security"></div>
<div class="typing-container" id="typing-text"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize particles
    if (document.getElementById("particles-js")) {
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 80 },
                "color": { "value": "#00e5ff" },
                "shape": { "type": "triangle" },
                "opacity": { "value": 0.5 },
                "size": { "value": 3 },
                "move": {
                    "enable": true,
                    "speed": 2
                }
            }
        });
    }

    // Initialize TomSelect on all .form-select elements
    document.querySelectorAll('.form-select').forEach(el => {
        if (!el.classList.contains('no-ts')) {
            new TomSelect(el, {
                create: el.multiple ? true : false,
                plugins: el.multiple ? ['remove_button'] : [],
                persist: false,
                dropdownParent: 'body',
            });
        }
    });

    // Typing effect for cyber phrase
    const phrases = [
        "Attackers only need to be right once.",
        "Defenders need to be right every time.",
        "Detection is half the battle;",
        "containment and recovery are the other half.",
        "You can't defend. You can only delay.",
    ];
    let phraseIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    const typingEl = document.getElementById('typing-text');

    function typeEffect() {
        const currentPhrase = phrases[phraseIndex];
        if (!isDeleting) {
            typingEl.textContent = currentPhrase.substring(0, charIndex + 1);
            charIndex++;
            if (charIndex === currentPhrase.length) {
                isDeleting = true;
                setTimeout(typeEffect, 2000);
                return;
            }
        } else {
            typingEl.textContent = currentPhrase.substring(0, charIndex - 1);
            charIndex--;
            if (charIndex === 0) {
                isDeleting = false;
                phraseIndex = (phraseIndex + 1) % phrases.length;
            }
        }
        const speed = isDeleting ? 30 : 60;
        setTimeout(typeEffect, speed + Math.random() * 30);
    }
    if (typingEl) typeEffect();
});
</script>
</body>
</html>
