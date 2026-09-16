import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    title: (title) => (title ? `${title} · Klik Laundry` : 'Klik Laundry'),
    pages: {
        path: './pages',
        extension: '.tsx',
        lazy: true,
    },
    strictMode: true,
});
