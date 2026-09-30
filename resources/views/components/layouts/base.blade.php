@props(['titre' => null, 'classeBody' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $titre ? $titre.' — ' : '' }}{{ App\Services\Parametres::nomApplication() }}</title>
        <link rel="icon" type="image/png" href="{{ App\Services\Parametres::logoPersonnalise() ? App\Services\Parametres::logoUrl() : asset('images/favicon-fg.png') }}">
        {{-- Application installable sur mobile (PWA) --}}
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#0b1257">
        <link rel="apple-touch-icon" href="{{ asset('images/icones/apple-touch-icon.png') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="ADVANTAGE">
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="{{ $classeBody }}">
        {{ $slot }}
        @stack('scripts')
    </body>
</html>
