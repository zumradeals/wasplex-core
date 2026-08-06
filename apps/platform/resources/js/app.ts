import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { configureEcho } from '@laravel/echo-vue';

// /broadcasting/auth passe par le middleware `web` (routes/channels.php via
// bootstrap/app.php) : cette app utilise des sessions cookie + CSRF
// XSRF-TOKEN (resources/js/lib/http.ts), pas de jeton Sanctum séparé — le
// transport interne de pusher-js ne passe pas par l'instance axios, donc le
// header doit être fourni explicitement ici.
function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

configureEcho({
    broadcaster: 'reverb',
    auth: {
        headers: {
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Wasplex';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4fa3ff',
    },
});
