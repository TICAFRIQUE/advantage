<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office : réservé aux comptes ayant au moins un rôle de l'espace
 * « gestion » (superadmin, admin, agent et rôles personnalisés). Les routes
 * restent en plus protégées par leur permission.
 */
class EspaceGestion
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->estDuBackOffice() === true, 403);

        return $next($request);
    }
}
