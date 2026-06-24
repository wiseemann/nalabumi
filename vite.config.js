import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const devServerUrl = process.env.VITE_DEV_SERVER_URL;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            ...(devServerUrl ? { server: { url: devServerUrl } } : {}),
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
        host: process.env.VITE_DEV_SERVER_HOST || 'localhost',
        port: Number(process.env.VITE_DEV_SERVER_PORT) || 5173,
        strictPort: true,
        watch: {
            usePolling: true,
        },
        allowedHosts: true,
    },
});
