import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            // Les icônes sont dans public/ — ne pas les copier dans build/
            includeAssets: [
                'favicon.ico',
                'icon.svg',
                'apple-touch-icon-180x180.png',
                'pwa-64x64.png',
                'pwa-192x192.png',
                'pwa-512x512.png',
                'maskable-icon-512x512.png',
            ],
            manifest: {
                name: 'App Magasin — LPO',
                short_name: 'Magasin',
                description: 'Gestion du magasin BTS — emprunts, équipements, réservations',
                theme_color: '#2563eb',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/',
                lang: 'fr',
                icons: [
                    { src: 'pwa-64x64.png',           sizes: '64x64',   type: 'image/png' },
                    { src: 'pwa-192x192.png',          sizes: '192x192', type: 'image/png' },
                    { src: 'pwa-512x512.png',          sizes: '512x512', type: 'image/png' },
                    { src: 'maskable-icon-512x512.png',sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // Ne pas mettre navigateFallback sur index.html
                // car Inertia/Laravel gère les routes côté serveur
                navigateFallback: null,
                // Fichiers pré-cachés (JS/CSS/fonts du build Vite)
                globPatterns: ['**/*.{js,css,woff2}'],
                runtimeCaching: [
                    // Fonts Bunny (Figtree) — cache 1 an
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts',
                            expiration: { maxEntries: 20, maxAgeSeconds: 365 * 24 * 60 * 60 },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    // Images statiques locales — cache 7 jours
                    {
                        urlPattern: /\/storage\/.+\.(png|jpg|jpeg|webp|svg)$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'local-images',
                            expiration: { maxEntries: 100, maxAgeSeconds: 7 * 24 * 60 * 60 },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    // Pages/API Laravel — toujours réseau en priorité
                    {
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkOnly',
                    },
                ],
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
});
