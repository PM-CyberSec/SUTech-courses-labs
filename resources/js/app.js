// import Pusher from 'pusher-js';
// import Echo from 'laravel-echo';
import Echo from 'laravel-echo';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'dlds-key',
    wsHost: '127.0.0.1',
    wsPort: 8080,
    forceTLS: false,
    disableStats: true,
});

window.Echo.channel('dlds-alerts')
    .listen('.new.alert', (e) => {
        console.log("LIVE ALERT:", e);
    });

// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
//     forceTLS: true,
// });

document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const btn = document.getElementById("toggleBtn");

    if (!sidebar || !btn) return;

    btn.addEventListener("click", () => {
        console.log("CLICKED");

        sidebar.classList.toggle("collapsed");
        sidebar.classList.toggle("expanded");
    });
});