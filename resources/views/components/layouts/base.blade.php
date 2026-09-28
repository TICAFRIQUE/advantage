@props(['titre' => null, 'classeBody' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $titre ? $titre.' — ' : '' }}{{ config('app.name', 'Advantage') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/favicon-fg.png') }}">
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="{{ $classeBody }}">
        {{ $slot }}
        @stack('scripts')
    </body>
</html>
