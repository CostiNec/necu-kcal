<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->currentLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#00A76F">
        <meta name="description" content="A calm, focused nutrition diary for calories and macros.">
        <meta name="application-name" content="{{ config('app.name', 'Kcal') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Kcal') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Kcal') }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <title inertia>{{ config('app.name', 'Kcal') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
