<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes HTTP de sécurité appliqués à toutes les réponses web.
 * (Une Content-Security-Policy stricte sera ajoutée en phase 7, avec le
 * build CSP d'Alpine.js qui n'exige pas 'unsafe-eval'.)
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $reponse->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Jamais de cache : les pages authentifiées contiennent des données
        // personnelles, et une page de formulaire restaurée depuis le cache
        // (bouton « retour ») porterait un jeton CSRF périmé (erreur 419).
        $reponse->headers->set('Cache-Control', 'no-store, private');

        return $reponse;
    }
}
