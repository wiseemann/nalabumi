import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    excludeAbsolute: true,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            'ziggy-js': '/node_modules/ziggy-js/dist/index.esm.js',
        },
    },
    server: {
        host: process.env.VITE_DEV_SERVER_HOST || '0.0.0.0',
        port: Number(process.env.VITE_DEV_SERVER_PORT) || 5173,
        strictPort: true,
        watch: {
            usePolling: true,
        },
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
        },
    },
});
