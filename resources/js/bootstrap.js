import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

// Only initialise Echo when Reverb credentials were baked in at build time.
// Missing vars (empty env on Forge) would make pusher-js throw and prevent
// React from mounting, causing a blank white page.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster:       'reverb',
        key:               import.meta.env.VITE_REVERB_APP_KEY,
        wsHost:            import.meta.env.VITE_REVERB_HOST,
        wsPort:            import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort:           import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS:          (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint:      '/broadcasting/auth',
        auth: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     csrfToken,
            },
        },
    });
}
