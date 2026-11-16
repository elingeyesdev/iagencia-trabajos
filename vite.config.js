import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin-dashboard.css',
                'resources/js/admin-dashboard.js',
                'resources/css/contratador-dashboard.css',
                'resources/js/contratador-dashboard.js',
                'resources/css/trabajador-dashboard.css',
                'resources/js/trabajador-dashboard.js',
                'resources/js/reportes.js',
                'resources/css/kpi-dashboard.css',
                'resources/js/kpi-dashboard.js',
                'resources/css/themes.css',
                'resources/js/themes.js',
            ],
            refresh: true,
        }),
    ],
});