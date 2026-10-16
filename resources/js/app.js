import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerCallStores } from './calls.js';
import { bridgeToEmbedder } from './embedBridge.js';
import { formatLocalTime, registerInviteStore } from './invites.js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => console.warn('Service worker registration failed:', error));
    });
}

// Alpine is started by Livewire on DOMContentLoaded, after this module has run.
document.addEventListener('alpine:init', () => {
    // Scheduled call times are sent in UTC and shown in the viewer's own time zone.
    window.Alpine.magic('localTime', () => formatLocalTime);

    if (window.authUserId) {
        registerCallStores(window.Alpine, window.Echo, window.authUserId);
        registerInviteStore(window.Alpine, window.Echo, window.authUserId, window.pendingInviteCount);
        bridgeToEmbedder(window.Alpine);
    }
});
