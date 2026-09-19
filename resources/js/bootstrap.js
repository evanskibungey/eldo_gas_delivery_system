import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

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

        // Authorise each private channel through axios rather than handing
        // pusher-js a CSRF token captured here.
        //
        // This file runs once, at page load. Signing in calls
        // session()->regenerate(), which mints a new CSRF token — and Inertia
        // does that login over XHR, so this module is never re-evaluated. A
        // token read here would be the PRE-login one for the rest of the
        // session, /broadcasting/auth would answer 419, and every private
        // subscription would fail. The panel does not go down when that
        // happens; it silently drops to polling and the new-order alarm never
        // rings. axios reads the XSRF-TOKEN cookie per request instead, which
        // Laravel rewrites on every response.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                    .then(response => callback(null, response.data))
                    .catch(error => callback(error, null));
            },
        }),
    });
}
