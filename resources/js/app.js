import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';

const appName = 'بوابة المجد التعليمية';
const siteThemes = ['royal', 'ocean', 'emerald', 'violet'];

const applySiteTheme = (theme) => {
    document.documentElement.dataset.siteTheme = siteThemes.includes(theme) ? theme : 'royal';
};

const pages = import.meta.glob('./Pages/**/*.vue');

// Preload the page component chunk immediately when Inertia prefetches a page
router.on('prefetched', (event) => {
    const component = event.detail?.response?.component;
    if (component && pages[`./Pages/${component}.vue`]) {
        pages[`./Pages/${component}.vue`]();
    }
});

// Preload and prefetch internal links on hover so page transitions feel instantaneous
if (typeof window !== 'undefined') {
    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a');
        if (!link || !link.href || link.target === '_blank') return;
        if (link.origin === window.location.origin) {
            const path = link.pathname;
            if (
                !path.startsWith('/api') &&
                !path.startsWith('/storage') &&
                !path.includes('/logout') &&
                !link.hasAttribute('download')
            ) {
                router.prefetch(link.href, { method: 'get' }, { cacheFor: '30s' });
            }
        }
    }, { passive: true });
}

createInertiaApp({
    title: (title) => `${title} — ${appName}`,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            pages,
        ),

    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        applySiteTheme(props.initialPage.props.settings?.site_theme);
        router.on('navigate', (event) => {
            applySiteTheme(event.detail.page.props.settings?.site_theme);
        });

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue);

        // Set RTL direction on the root element
        el.setAttribute('dir', 'rtl');
        el.setAttribute('lang', 'ar');

        return app.mount(el);
    },

    progress: {
        color:    '#28a18d',  // primary-500
        showSpinner: false,
    },
});
