import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';

declare global {
    interface Window {
        // Optional on purpose: bootstrap.js only constructs Echo when the
        // Reverb key was baked in at build time. Typing it as always-present
        // is what let an unguarded `window.Echo.private(...)` ship.
        Echo:   Echo | undefined;
        Pusher: typeof Pusher;
    }
}
