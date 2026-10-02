<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Gestion\SupprimerCarteDefinitivementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\SupprimerCarteRequest;
use App\Models\Carte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Suppression définitive d'une carte et de tout son historique (cartes de
 * test). Routes protégées par la permission dédiée et le mot de passe
 * confirmé depuis moins de 5 minutes.
 */
class SuppressionCarteController extends Controller
{
    /**
     * Page de confirmation : ce qui sera effacé, chiffres à l'appui.
     */
    public function create(Carte $carte): View
    {
        Gate::authorize('supprimerDefinitivement', $carte);

        $carte->load('titulaire');

        return view('gestion.cartes.supprimer', [
            'carte' => $carte,
            'transactions' => $carte->transactions()->count(),
            'partenaires' => $carte->transactions()->distinct()->count('partenaire_id'),
            'codes' => $carte->demandesOtp()->count(),
            'alertes' => $carte->alertesExpiration()->count(),
            'operations' => $carte->operations()->count(),
            'autresCartes' => Carte::withTrashed()->where('titulaire_id', $carte->titulaire_id)->whereKeyNot($carte->id)->count(),
        ]);
    }

    public function destroy(SupprimerCarteRequest $request, Carte $carte, SupprimerCarteDefinitivementAction $supprimer): RedirectResponse
    {
        $suppression = $supprimer($carte, $request->user(), $request->validated('motif'));

        return redirect()->route('gestion.cartes.index')->with('succes', sprintf(
            'La carte %s a été supprimée définitivement (%d transaction(s) effacée(s)%s). Son numéro peut être activé de nouveau.',
            $suppression->numeroFormate(),
            $suppression->transactions_supprimees,
            $suppression->titulaire_supprime ? ', titulaire supprimé' : '',
        ));
    }
}
