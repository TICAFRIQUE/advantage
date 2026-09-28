<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Comptes\CreerCompteAction;
use App\Enums\Role;
use App\Exceptions\OperationCompteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\CreerOperateurRequest;
use App\Models\Partenaire;
use Illuminate\Http\RedirectResponse;

/**
 * Création d'un opérateur rattaché à un partenaire. Le PIN est affiché une
 * seule fois (message flash consommé à la requête suivante).
 */
class OperateurController extends Controller
{
    public function store(CreerOperateurRequest $request, Partenaire $partenaire, CreerCompteAction $creer): RedirectResponse
    {
        try {
            ['compte' => $compte, 'pin' => $pin] = $creer($request->donnees(), Role::Partenaire, $request->user(), $partenaire);
        } catch (OperationCompteException $exception) {
            return back()->withInput()->withErrors(['nom_utilisateur' => $exception->getMessage()]);
        }

        return redirect()->route('gestion.partenaires.show', $partenaire)
            ->with('pin_genere', ['nom' => $compte->nom, 'nom_utilisateur' => $compte->nom_utilisateur, 'pin' => $pin]);
    }
}
