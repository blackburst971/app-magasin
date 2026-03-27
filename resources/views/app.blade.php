<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'App Magasin') }}</title>

        <!-- PWA — icônes & manifest -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png">

        <!-- PWA — iOS Safari -->
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Magasin">
        <meta name="theme-color" content="#2563eb">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50">
        @inertia
    </body>
</html>
