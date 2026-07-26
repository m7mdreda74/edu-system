import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            // ─── Typography ───────────────────────────────────────────
            fontFamily: {
                sans:  ['Cairo', 'Tajawal', ...defaultTheme.fontFamily.sans],
                latin: ['Inter', ...defaultTheme.fontFamily.sans],
                cairo: ['Cairo', 'sans-serif'],
            },

            // ─── Color Palette ────────────────────────────────────────
            // Runtime theme palettes. Each color is backed by CSS variables so
            // the admin can switch the whole application theme without a rebuild.
            colors: {
                primary: {
                    50:  'rgb(var(--primary-50) / <alpha-value>)',
                    100: 'rgb(var(--primary-100) / <alpha-value>)',
                    200: 'rgb(var(--primary-200) / <alpha-value>)',
                    300: 'rgb(var(--primary-300) / <alpha-value>)',
                    400: 'rgb(var(--primary-400) / <alpha-value>)',
                    500: 'rgb(var(--primary-500) / <alpha-value>)',
                    600: 'rgb(var(--primary-600) / <alpha-value>)',
                    700: 'rgb(var(--primary-700) / <alpha-value>)',
                    800: 'rgb(var(--primary-800) / <alpha-value>)',
                    900: 'rgb(var(--primary-900) / <alpha-value>)',
                    950: 'rgb(var(--primary-950) / <alpha-value>)',
                },
                accent: {
                    50:  'rgb(var(--accent-50) / <alpha-value>)',
                    100: 'rgb(var(--accent-100) / <alpha-value>)',
                    200: 'rgb(var(--accent-200) / <alpha-value>)',
                    300: 'rgb(var(--accent-300) / <alpha-value>)',
                    400: 'rgb(var(--accent-400) / <alpha-value>)',
                    500: 'rgb(var(--accent-500) / <alpha-value>)',
                    600: 'rgb(var(--accent-600) / <alpha-value>)',
                    700: 'rgb(var(--accent-700) / <alpha-value>)',
                    800: 'rgb(var(--accent-800) / <alpha-value>)',
                    900: 'rgb(var(--accent-900) / <alpha-value>)',
                    950: 'rgb(var(--accent-950) / <alpha-value>)',
                },
                surface: {
                    50:  'rgb(var(--surface-50) / <alpha-value>)',
                    100: 'rgb(var(--surface-100) / <alpha-value>)',
                    200: 'rgb(var(--surface-200) / <alpha-value>)',
                    300: 'rgb(var(--surface-300) / <alpha-value>)',
                    400: 'rgb(var(--surface-400) / <alpha-value>)',
                    // Half-steps: used for muted hints and elevated dark panels.
                    450: 'rgb(var(--surface-450) / <alpha-value>)',
                    500: 'rgb(var(--surface-500) / <alpha-value>)',
                    550: 'rgb(var(--surface-550) / <alpha-value>)',
                    600: 'rgb(var(--surface-600) / <alpha-value>)',
                    700: 'rgb(var(--surface-700) / <alpha-value>)',
                    800: 'rgb(var(--surface-800) / <alpha-value>)',
                    850: 'rgb(var(--surface-850) / <alpha-value>)',
                    900: 'rgb(var(--surface-900) / <alpha-value>)',
                    950: 'rgb(var(--surface-950) / <alpha-value>)',
                },
            },

            // ─── Spacing ──────────────────────────────────────────────
            spacing: {
                '18': '4.5rem',
                '22': '5.5rem',
                '88': '22rem',
                '128': '32rem',
            },

            // ─── Border Radius ────────────────────────────────────────
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
                '4xl': '2rem',
            },

            // ─── Box Shadow ───────────────────────────────────────────
            boxShadow: {
                'glow-primary': '0 0 20px rgb(var(--primary-500) / 0.3)',
                'glow-accent':  '0 0 20px rgb(var(--accent-500) / 0.3)',
                'card':         '0 2px 15px -3px rgba(0,0,0,0.07), 0 10px 20px -2px rgba(0,0,0,0.04)',
                'card-hover':   '0 10px 40px -10px rgba(0,0,0,0.15)',
            },

            // ─── Animation ────────────────────────────────────────────
            transitionTimingFunction: {
                'spring': 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
            },

            keyframes: {
                'fade-up': {
                    '0%':   { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%':   { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'shimmer': {
                    '0%':   { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'pulse-ring': {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgb(var(--primary-500) / 0.4)' },
                    '50%':      { boxShadow: '0 0 0 8px rgb(var(--primary-500) / 0)' },
                },
            },

            animation: {
                'fade-up':    'fade-up 0.5s ease-out forwards',
                'fade-in':    'fade-in 0.3s ease-out forwards',
                'shimmer':    'shimmer 2s infinite',
                'pulse-ring': 'pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
        },
    },

    plugins: [forms, typography],
};
