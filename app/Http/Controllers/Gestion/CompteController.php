<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Comptes\GererCompteAction;
use App\Actions\Comptes\ModifierCompteAction;
use App\Enums\Role;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ModifierCompteRequest;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Opérations sur un compte (opérateur, agent, admin) : PIN, verrouillage,
 * statut, suppression (archivage), modification de la fiche. Autorisation par UserPolicy::gerer (GardeDroits), revérifiée dans
 * l'action.
 */
class CompteController extends Controller
{
    public function edit(User $compte): View
    {
        Gate::authorize('gerer', $compte);

        return view('gestion.comptes.modifier', [
            'compte' => $compte,
            // Le rôle d'un utilisateur de partenaire ne change pas.
            'roles' => $compte->hasRole(Role::Partenaire) ? [] : collect(GardeDroits::rolesGestionAttribuables(auth()->user(), $compte))
                ->push($compte->rolePrincipal())->filter()->unique('name')->values()->all(),
            'pays' => Telephone::tousLesPays(),
            'retour' => $this->retour($compte),
        ]);
    }

    public function update(ModifierCompteRequest $request, User $compte, ModifierCompteAction $modifier): RedirectResponse
    {
        try {
            $modifier($compte, $request->donnees(), $request->role(), $request->user());
        } catch (OperationCompteException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect($this->retour($compte))->with('succes', "Le compte {$compte->nom_utilisateur} a été mis à jour.");
    }

    public function reinitialiserPin(User $compte, GererCompteAction $gerer): RedirectResponse
    {
        Gate::authorize('reinitialiserPin', $compte);

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
     * Page d'origine du compte : fiche du partenaire ou fiche utilisateur.
     */
    private function retour(User $compte): string
    {
        return $compte->partenaire_id !== null
            ? route('gestion.partenaires.show', $compte->partenaire_id)
            : route('gestion.utilisateurs.show', $compte);
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
