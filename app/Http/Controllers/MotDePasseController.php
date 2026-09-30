<?php

namespace App\Http\Controllers;

use App\Actions\Comptes\MotDePasseSuperadminAction;
use App\Exceptions\OperationCompteException;
use App\Http\Requests\ModifierMotDePasseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mon profil › Mot de passe (superadmin uniquement, mot de passe actuel
 * confirmé depuis moins de 5 minutes) : le choisir ou en générer un nouveau.
 */
class MotDePasseController extends Controller
{
    public function modifier(ModifierMotDePasseRequest $request, MotDePasseSuperadminAction $action): RedirectResponse
    {
        try {
            $action->definir($request->user(), $request->validated('mot_de_passe'));
        } catch (OperationCompteException $exception) {
            return to_route('profil')->with('erreur', $exception->getMessage());
        }

        return to_route('profil')->with('succes', 'Mot de passe modifié.');
    }

    public function generer(Request $request, MotDePasseSuperadminAction $action): RedirectResponse
    {
        try {
            $motDePasse = $action->generer($request->user());
        } catch (OperationCompteException $exception) {
            return to_route('profil')->with('erreur', $exception->getMessage());
        }

        return to_route('profil')->with('pin_genere', [
            'nom' => $request->user()->nom,
            'nom_utilisateur' => $request->user()->nom_utilisateur,
            'pin' => $motDePasse,
            'personnel' => true,
        ]);
    }
}
