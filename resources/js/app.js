import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';

const appName = 'منصة التفوق';
const siteThemes = ['royal', 'ocean', 'emerald', 'violet'];

const applySiteTheme = (theme) => {
    document.documentElement.dataset.siteTheme = siteThemes.includes(theme) ? theme : 'royal';
};

createInertiaApp({
    title: (title) => `${title} — ${appName}`,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
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
