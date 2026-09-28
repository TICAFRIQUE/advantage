<?php

use App\Http\Middleware\CompteActif;
use App\Http\Middleware\EntetesSecurite;
use App\Http\Middleware\PartenaireActif;
use App\Http\Middleware\PartenaireCourantRequis;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'compte.actif' => CompteActif::class,
            'partenaire.actif' => PartenaireActif::class,
            'partenaire.courant' => PartenaireCourantRequis::class,
        ]);

        $middleware->web(append: [EntetesSecurite::class]);

        // Préférence d'affichage écrite en JavaScript (aucune donnée sensible).
        $middleware->encryptCookies(except: ['barre_reduite']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('accueil-espace'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Jamais réaffichés ni stockés en session après une erreur de validation.
        $exceptions->dontFlash(['pin', 'code', 'numero_piece_identite']);

        // Jeton CSRF expiré (419) : retour au formulaire avec un message clair
        // plutôt que la page d'erreur brute. La saisie sensible n'est pas conservée.
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $message = __('Votre page a expiré. Veuillez réessayer.');

            return $request->routeIs('login.store')
                ? redirect()->route('login')->withInput($request->only('nom_utilisateur'))->withErrors(['nom_utilisateur' => $message])
                : back()->with('erreur', $message);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
