import '../css/app.css';
import './i18n';
import '@fontsource-variable/public-sans';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/theme/theme-provider';
import { Snackbar } from '@/components/snackbar';

const appName = import.meta.env.VITE_APP_NAME || 'Kcal';
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
                <App {...props} />
                <Snackbar />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#00A76F',
    },
});
