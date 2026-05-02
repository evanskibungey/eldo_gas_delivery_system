import './bootstrap';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

let _appName = 'EldoGas';

createInertiaApp({
    title: (title) => (title ? `${title} — ${_appName}` : _appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        _appName = (props.initialPage.props as any).app_name ?? 'EldoGas';
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#f97316',
    },
});
