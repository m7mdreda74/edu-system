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
            // Qatari Maroon (Burgundy/العنابي) + Amber accent — premium Qatari feel
            colors: {
                primary: {
                    50:  '#fdf2f4',
                    100: '#fbe5e9',
                    200: '#f7ccd5',
                    300: '#f1a3b5',
                    400: '#e46e8c',
                    500: '#8D1C3D', // Qatar Maroon
                    600: '#7A1732',
                    700: '#661127',
                    800: '#540d1e',
                    900: '#460c1b',
                    950: '#26040d',
                },
                accent: {
                    50:  '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                surface: {
                    50:  '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
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
                'glow-primary': '0 0 20px rgba(141, 28, 61, 0.3)',
                'glow-accent':  '0 0 20px rgba(245, 158, 11, 0.3)',
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
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(141, 28, 61, 0.4)' },
                    '50%':      { boxShadow: '0 0 0 8px rgba(141, 28, 61, 0)' },
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
