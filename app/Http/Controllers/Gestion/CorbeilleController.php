<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Corbeille\RestaurerAction;
use App\Exceptions\RestaurationException;
use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Éléments supprimés (archivés) : partenaires et comptes, avec l'auteur de
 * la suppression, et leur restauration. Superadmin uniquement (routes).
 */
class CorbeilleController extends Controller
{
    public function index(): View
    {
        return view('gestion.corbeille.index', [
            'partenaires' => Partenaire::onlyTrashed()
                ->with('supprimePar.roles')
                ->withCount(['operateurs as utilisateurs_supprimes_count' => fn ($q) => $q->onlyTrashed()])
                ->latest('deleted_at')->latest('id')
                ->paginate(15, pageName: 'page_partenaires'),
            'comptes' => User::onlyTrashed()
                ->with(['roles', 'supprimePar.roles', 'partenaire' => fn ($q) => $q->withTrashed()])
                ->latest('deleted_at')->latest('id')
                ->paginate(15, pageName: 'page_comptes'),
        ]);
    }

    public function restaurerPartenaire(Request $request, Partenaire $partenaire, RestaurerAction $restaurer): RedirectResponse
    {
        return $this->executer(function () use ($request, $partenaire, $restaurer): RedirectResponse {
            $nombre = $restaurer->partenaire($partenaire, $request->user(), $request->boolean('avec_utilisateurs'));

            return back()->with('succes', "Le partenaire {$partenaire->nom} est restauré"
                .($nombre > 0 ? " avec {$nombre} utilisateur(s)." : '.'));
        });
    }

    public function restaurerCompte(Request $request, User $compte, RestaurerAction $restaurer): RedirectResponse
    {
        return $this->executer(function () use ($request, $compte, $restaurer): RedirectResponse {
            $restaurer->compte($compte, $request->user());

            return back()->with('succes', "Le compte {$compte->nom_utilisateur} est restauré : il peut de nouveau se connecter avec son PIN.");
        });
    }

    /**
     * @param  callable(): RedirectResponse  $operation
     */
    private function executer(callable $operation): RedirectResponse
    {
        try {
            return $operation();
        } catch (RestaurationException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }
    }
}
