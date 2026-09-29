<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes HTTP de sécurité appliqués à toutes les réponses web, dont une
 * Content-Security-Policy stricte : scripts uniquement depuis l'application
 * (aucun script en ligne, aucune évaluation — Alpine en version CSP),
 * aucune ressource externe, aucune intégration dans un cadre.
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
        $reponse->headers->set('Content-Security-Policy', $this->politique());

        if ($request->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Jamais de cache : les pages authentifiées contiennent des données
        // personnelles, et une page de formulaire restaurée depuis le cache
        // (bouton « retour ») porterait un jeton CSRF périmé (erreur 419).
        $reponse->headers->set('Cache-Control', 'no-store, private');

        return $reponse;
    }

    private function politique(): string
    {
        // Développement (npm run dev) : scripts, styles et rechargement servis par Vite.
        $vite = Vite::isRunningHot() ? rtrim((string) file_get_contents(Vite::hotFile())) : null;
        $sources = fn (?string ...$autres) => implode(' ', array_filter(["'self'", ...$autres, $vite]));

        return implode('; ', [
            "default-src 'self'",
            'script-src '.$sources(),
            // Styles en ligne : SweetAlert2, DataTables et Bootstrap positionnent
            // leurs éléments par attribut style (aucun script ne peut s'y loger).
            'style-src '.$sources("'unsafe-inline'"),
            "img-src 'self' data:",
            "font-src 'self' data:",
            'connect-src '.$sources($vite ? str_replace('http', 'ws', $vite) : null),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
