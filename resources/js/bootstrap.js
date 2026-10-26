import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getClientTimeZone } from './lib/timezone';
window.axios = axios;
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || window.location.port || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || window.location.port || 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '')) === 'https',
    enabledTransports: ['ws', 'wss'],
});

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['X-Client-Timezone'] = getClientTimeZone();
