<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Advantage') }} — fontaine GROUP</title>
        <link rel="icon" type="image/png" href="{{ asset('images/favicon-fg.png') }}">
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    </head>
    <body class="fond-nuit min-vh-100 d-flex align-items-center">
        <main class="container py-5 text-center">
            <img src="{{ asset('images/logo-fg.png') }}" alt="fontaine GROUP" width="96" height="96" class="rounded mb-4">
            <p class="texte-eau fw-semibold mb-1">fontaine GROUP</p>
            <h1 class="display-4 fw-bolder text-white mb-3">ADVANTAGE</h1>
            <p class="lead text-white-50 mb-4">Carte de remise universelle — validation par code à usage unique.</p>
            <img src="{{ asset('images/papillon-or.png') }}" alt="" width="180" class="img-fluid mb-4" aria-hidden="true">
            <div>
                @auth
                    <a href="{{ route('accueil-espace') }}" class="btn btn-encre btn-lg px-5">Accéder à mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-encre btn-lg px-5">Se connecter</a>
                @endauth
            </div>
        </main>
    </body>
</html>
