import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        define: {
            'import.meta.env.VITE_PUSHER_APP_KEY': JSON.stringify(env.VITE_PUSHER_APP_KEY ?? ''),
            'import.meta.env.VITE_PUSHER_APP_CLUSTER': JSON.stringify(env.VITE_PUSHER_APP_CLUSTER ?? 'mt1'),
            'import.meta.env.VITE_PUSHER_HOST': JSON.stringify(env.VITE_PUSHER_HOST ?? ''),
            'import.meta.env.VITE_PUSHER_PORT': JSON.stringify(env.VITE_PUSHER_PORT ?? '443'),
            'import.meta.env.VITE_PUSHER_SCHEME': JSON.stringify(env.VITE_PUSHER_SCHEME ?? 'https'),
        },
    };
});
