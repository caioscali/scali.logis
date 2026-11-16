import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

createInertiaApp({
    title: (title) => (title ? title + ' · Scali Logis' : 'Scali Logis'),
    resolve: (name) => resolvePageComponent<{
        default: ComponentType;
    }>('./Pages/' + name + '.tsx', import.meta.glob('./Pages/**/*.tsx') as Record<string, () => Promise<{
        default: ComponentType;
    }>>).then((module) => module.default),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0284c7',
    },
});
