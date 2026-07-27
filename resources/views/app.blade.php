<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->currentLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#00A76F">
        <meta name="description" content="A calm, focused nutrition diary for calories and macros.">
        <meta name="application-name" content="{{ config('app.name', 'NecuTrack') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'NecuTrack') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'NecuTrack') }}">
        <link rel="icon" href="/icons/favicon-32-v3.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon-v2.png" sizes="180x180">
        <link rel="manifest" href="/manifest.webmanifest">
        <title inertia>{{ config('app.name', 'NecuTrack') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
