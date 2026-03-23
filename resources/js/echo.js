import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// 智能端口配置：当使用 HTTPS 且端口是标准端口时，不设置端口号
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https'
const isHttps = scheme === 'https'
const port = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : (isHttps ? 443 : 8080)

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'jnwliwk8ulk32jkwqcy7',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'ws.game.dogeow.com',
    wsPort: isHttps ? (port === 443 ? undefined : port) : port,
    wssPort: isHttps ? (port === 443 ? undefined : port) : port,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});
