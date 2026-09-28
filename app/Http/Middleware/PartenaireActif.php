<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un opérateur partenaire n'accède à son espace que s'il est rattaché à un
 * partenaire actif. Le superadmin et l'admin y accèdent sans rattachement
 * (les actions exigeant un partenaire le vérifieront elles-mêmes).
 */
class PartenaireActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->hasAnyRole([Role::Superadmin, Role::Admin])) {
            return $next($request);
        }

        abort_unless($user->partenaire?->estActif() === true, 403, __('Aucun partenaire actif n\'est associé à ce compte.'));

        return $next($request);
    }
}
