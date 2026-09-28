<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un opérateur partenaire n'accède à son espace que s'il est rattaché à un
 * partenaire actif (l'espace partenaire est réservé au rôle partenaire).
 */
class PartenaireActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user->partenaire?->estActif() === true, 403, __('Aucun partenaire actif n\'est associé à ce compte.'));

        return $next($request);
    }
}
