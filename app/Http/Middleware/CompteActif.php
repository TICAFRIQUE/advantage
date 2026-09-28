<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement la session d'un compte verrouillé, désactivé ou
 * supprimé, ainsi que toute session ayant dépassé sa durée maximale.
 */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $message = match (true) {
            $user->trashed() || ! $user->estActif() => __('Votre compte n\'est plus actif. Contactez un administrateur.'),
            $this->sessionExpiree($request) => __('Votre session a expiré. Veuillez vous reconnecter.'),
            default => null,
        };

        if ($message === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route('login')->withErrors(['nom_utilisateur' => $message]);
    }

    private function sessionExpiree(Request $request): bool
    {
        $connecteLe = $request->session()->get('connecte_le');
        $dureeMax = (int) config('plateforme.connexion.duree_max_session_minutes') * 60;

        return $connecteLe === null || (now()->getTimestamp() - (int) $connecteLe) > $dureeMax;
    }
}
