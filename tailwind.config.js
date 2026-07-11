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
            // Luxurious Burgundy (العنابي) + Metallic Gold (الذهبي)
            colors: {
                primary: {
                    50:  '#faf4f5',
                    100: '#f5e2e6',
                    200: '#e8b2bf',
                    300: '#d57d93',
                    400: '#ac3a55', // Deep premium crimson/burgundy tone (no hot pink)
                    500: '#7A1C37', // Luxurious Burgundy
                    600: '#68142c',
                    700: '#560d22',
                    800: '#46091b',
                    900: '#3a0817',
                    950: '#22030c',
                },
                accent: {
                    50:  '#fbfaf3',
                    100: '#faf3df',
                    200: '#f4e5b9',
                    300: '#eacc80',
                    400: '#dfaf49',
                    500: '#C5A039', // Luxury Gold
                    600: '#a7842c',
                    700: '#826420',
                    800: '#5d4617',
                    900: '#3e2e0f',
                    950: '#261c0a',
                },
                surface: {
                    50:  '#faf8f6',
                    100: '#f4efea',
                    200: '#e8dfd7',
                    300: '#d2c4b7',
                    400: '#9f8c7f',
                    500: '#776558',
                    600: '#5f4e43',
                    700: '#33272c',
                    800: '#1c1416',
                    900: '#130b0d',
                    950: '#0a0405',
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
                'glow-primary': '0 0 20px rgba(122, 28, 55, 0.3)',
                'glow-accent':  '0 0 20px rgba(197, 160, 57, 0.3)',
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
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(122, 28, 55, 0.4)' },
                    '50%':      { boxShadow: '0 0 0 8px rgba(122, 28, 55, 0)' },
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
