import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'media',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: 'var(--color-brand-50)',
                    100: 'var(--color-brand-100)',
                    200: 'var(--color-brand-200)',
                    300: 'var(--color-brand-300)',
                    400: 'var(--color-brand-400)',
                    500: 'var(--color-brand-500)',
                    600: 'var(--color-brand-600)',
                    700: 'var(--color-brand-700)',
                    800: 'var(--color-brand-800)',
                    900: 'var(--color-brand-900)',
                    
                    // Semantic Aliases
                    'bg-dark': 'var(--color-brand-bg-dark)',
                    primary: 'var(--color-brand-primary)',
                    'primary-hover': 'var(--color-brand-primary-hover)',
                    accent: 'var(--color-brand-accent)',
                    'accent-soft': 'var(--color-brand-accent-soft)',
                    ring: 'var(--color-brand-ring)',
                    'text-on-brand': 'var(--color-brand-text-on-brand)',
                }
            }
        },
    },

    plugins: [forms],
};