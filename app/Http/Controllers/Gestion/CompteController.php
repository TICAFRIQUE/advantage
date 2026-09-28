<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Comptes\GererCompteAction;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Opérations sur un compte (opérateur, agent, admin) : PIN, verrouillage,
 * statut, suppression (archivage). Autorisation par UserPolicy::gerer (GardeDroits), revérifiée dans
 * l'action.
 */
class CompteController extends Controller
{
    public function reinitialiserPin(User $compte, GererCompteAction $gerer): RedirectResponse
    {
        Gate::authorize('gerer', $compte);

        return $this->executer(function () use ($compte, $gerer): RedirectResponse {
            $pin = $gerer->reinitialiserPin($compte, auth()->user());

            return back()->with('pin_genere', ['nom' => $compte->nom, 'nom_utilisateur' => $compte->nom_utilisateur, 'pin' => $pin]);
        });
    }

    public function verrouillage(Request $request, User $compte, GererCompteAction $gerer): RedirectResponse
    {
        Gate::authorize('gerer', $compte);

        $verrouiller = $request->boolean('verrouiller');

        return $this->executer(function () use ($compte, $gerer, $verrouiller): RedirectResponse {
            $verrouiller ? $gerer->verrouiller($compte, auth()->user()) : $gerer->deverrouiller($compte, auth()->user());

            return back()->with('succes', $verrouiller ? "Le compte {$compte->nom_utilisateur} est verrouillé." : "Le compte {$compte->nom_utilisateur} est déverrouillé.");
        });
    }

    public function statut(Request $request, User $compte, GererCompteAction $gerer): RedirectResponse
    {
        Gate::authorize('gerer', $compte);

        $statut = StatutUtilisateur::from($request->validate([
            'statut' => ['required', 'in:'.StatutUtilisateur::Actif->value.','.StatutUtilisateur::Inactif->value],
        ])['statut']);

        return $this->executer(function () use ($compte, $gerer, $statut): RedirectResponse {
            $gerer->changerStatut($compte, $statut, auth()->user());

            return back()->with('succes', $statut === StatutUtilisateur::Actif
                ? "Le compte {$compte->nom_utilisateur} est réactivé."
                : "Le compte {$compte->nom_utilisateur} est désactivé.");
        });
    }

    public function supprimer(User $compte, GererCompteAction $gerer): RedirectResponse
    {
        Gate::authorize('supprimer', $compte);

        return $this->executer(function () use ($compte, $gerer): RedirectResponse {
            $gerer->supprimer($compte, auth()->user());

            return back()->with('succes', "Le compte {$compte->nom_utilisateur} a été supprimé. Son nom reste visible dans l'historique.");
        });
    }

    /**
     * @param  callable(): RedirectResponse  $operation
     */
    private function executer(callable $operation): RedirectResponse
    {
        try {
            return $operation();
        } catch (OperationCompteException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }
    }
}
