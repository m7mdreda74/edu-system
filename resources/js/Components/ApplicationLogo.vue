<template>
    <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="التفوق" v-bind="$attrs">
        <defs>
            <linearGradient id="brand-bg" x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
                <stop stop-color="rgb(var(--primary-500, 124 58 237))" />
                <stop offset="1" stop-color="rgb(var(--primary-700, 91 33 182))" />
            </linearGradient>
            <linearGradient id="brand-gold" x1="18" y1="18" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                <stop stop-color="rgb(var(--accent-400, 250 204 21))" />
                <stop offset="1" stop-color="rgb(var(--accent-600, 202 138 4))" />
            </linearGradient>
        </defs>
        <rect x="3" y="3" width="58" height="58" rx="17" fill="url(#brand-bg)" />
        <path d="M16 25.5 32 18l16 7.5-16 7.5-16-7.5Z" fill="url(#brand-gold)" />
        <path d="M20 29v8.4c0 3.8 5.4 7.6 12 7.6s12-3.8 12-7.6V29" fill="none" stroke="white" stroke-width="2.8" stroke-linecap="round" />
        <path d="M48 26v11" stroke="white" stroke-width="2.8" stroke-linecap="round" />
        <circle cx="48" cy="40.5" r="2.2" fill="url(#brand-gold)" />
        <path d="m32 12 1.1 2.5 2.7.3-2 1.8.6 2.6-2.4-1.4-2.4 1.4.6-2.6-2-1.8 2.7-.3L32 12Z" fill="white" />
    </svg>
</template>
