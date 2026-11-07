import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Dashboard bundle: does NOT start Alpine (Livewire provides it
                // on dashboard pages), so it can't clash with app.js.
                'resources/js/dashboard.js',
            ],
            // Document root is one level above this app dir, so the build
            // output (and hot file) must land in ../build, served as /build/...
            publicDirectory: '../',
            refresh: true,
        }),
    ],
});
