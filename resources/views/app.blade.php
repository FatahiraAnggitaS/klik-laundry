<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#F4F8FC">

        <script>
            (() => {
                try {
                    const preference = localStorage.getItem('klik-laundry-theme');
                    const dark = preference === 'dark'
                        || (preference !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                    document.querySelector('meta[name="theme-color"]').content = dark ? '#071521' : '#F4F8FC';
                } catch (_) {
                    // Keep the safe light fallback when browser storage is unavailable.
                }
            })();
        </script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])

        <x-inertia::head>
            <title>{{ config('app.name', 'Klik Laundry') }}</title>
            <meta
                name="description"
                content="Platform operasional laundry multi-tenant untuk customer, tenant, driver, dan tim platform."
            >
        </x-inertia::head>
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>
