import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Colors map to CSS custom properties (RGB channels) defined in
 * resources/css/app.css, so light/dark theming and future rebrands are a
 * one-file change. Use like: bg-surface, text-app-text, border-line,
 * bg-primary, text-primary-fg, text-muted, bg-success/warning/danger.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // App chrome / surfaces
                bg: 'rgb(var(--hm-bg) / <alpha-value>)',
                surface: 'rgb(var(--hm-surface) / <alpha-value>)',
                card: 'rgb(var(--hm-card) / <alpha-value>)',
                line: 'rgb(var(--hm-border) / <alpha-value>)',
                'app-text': 'rgb(var(--hm-text) / <alpha-value>)',
                muted: 'rgb(var(--hm-muted) / <alpha-value>)',
                // Brand / semantic
                primary: {
                    DEFAULT: 'rgb(var(--hm-primary) / <alpha-value>)',
                    fg: 'rgb(var(--hm-primary-fg) / <alpha-value>)',
                    soft: 'rgb(var(--hm-primary-soft) / <alpha-value>)',
                },
                success: 'rgb(var(--hm-success) / <alpha-value>)',
                warning: 'rgb(var(--hm-warning) / <alpha-value>)',
                danger: 'rgb(var(--hm-danger) / <alpha-value>)',
            },
            ringColor: {
                brand: 'rgb(var(--hm-ring) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
