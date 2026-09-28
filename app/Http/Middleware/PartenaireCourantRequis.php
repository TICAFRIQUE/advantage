<?php

namespace App\Http\Middleware;

use App\Services\PartenaireCourant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les opérations partenaire (vérification, code, transactions) exigent de
 * savoir pour quel partenaire on agit. Un admin sans choix est renvoyé vers
 * le sélecteur.
 */
class PartenaireCourantRequis
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PartenaireCourant::pour($request->user()) !== null) {
            return $next($request);
        }

        if (PartenaireCourant::peutChoisir($request->user())) {
            return redirect()->route('partenaire.tableau-de-bord')
                ->with('erreur', 'Choisissez d\'abord le partenaire pour le compte duquel vous agissez.');
        }

        abort(403, __('Aucun partenaire actif n\'est associé à ce compte.'));
    }
}
