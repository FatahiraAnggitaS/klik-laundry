import { createInertiaApp } from '@inertiajs/react';
import { PublicShell } from '@/layouts/public-shell';
import { WorkspaceShell } from '@/layouts/workspace-shell';
import { ThemeProvider } from '@/theme';

function defaultLayout(name: string) {
    if (name.startsWith('outlets/')) return PublicShell;

    const standalone = name.startsWith('auth/')
        || name.startsWith('dashboard/')
        || name.startsWith('errors/')
        || name.startsWith('foundation/')
        || name.startsWith('milestone-zero/')
        || name.endsWith('/receipt');

    return standalone ? undefined : WorkspaceShell;
}

createInertiaApp({
    title: (title) => (title ? `${title} · Klik Laundry` : 'Klik Laundry'),
    pages: {
        path: './pages',
        extension: '.tsx',
        lazy: true,
    },
    strictMode: true,
    layout: defaultLayout,
    progress: {
        delay: 200,
        color: '#0EA5E9',
        includeCSS: true,
        showSpinner: false,
    },
    withApp: (app) => <ThemeProvider>{app}</ThemeProvider>,
});
