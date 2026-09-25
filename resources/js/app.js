import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerCallStores } from './calls.js';

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
    if (window.authUserId) {
        registerCallStores(window.Alpine, window.Echo, window.authUserId);
    }
});
