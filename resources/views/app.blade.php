<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#123836">

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
