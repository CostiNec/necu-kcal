import '../css/app.css';
import './i18n';
import '@fontsource-variable/public-sans';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { NumberInputBehavior } from '@/components/number-input-behavior';
import { ThemeProvider } from '@/theme/theme-provider';
import { Snackbar } from '@/components/snackbar';
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'NecuTrack';
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Unknown page: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        if (!el) return;

        createRoot(el).render(
            <ThemeProvider>
                <NumberInputBehavior />
                <App {...props} />
                <Snackbar />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#00A76F',
    },
});

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}
