<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\Permission;
use App\Enums\StatutCarte;
use App\Enums\StatutPartenaire;
use App\Http\Controllers\Controller;
use App\Models\Carte;
use App\Models\Partenaire;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Vue d'ensemble du back-office : seuls les indicateurs que l'utilisateur a
 * le droit de voir sont calculés et affichés (requêtes SQL agrégées).
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('gestion.tableau-de-bord', [
            'cartes' => $user->can(Permission::VoirCartes->value) ? $this->indicateursCartes($user->id) : null,
            'transactions' => $user->can(Permission::VoirRapportTransactions->value) ? $this->indicateursTransactions() : null,
            'expirations' => $user->can(Permission::VoirCartes->value) ? $this->expirationsProches() : null,
            'dernieres' => $user->can(Permission::ActiverCarte->value)
                ? Carte::query()->with('titulaire')->where('active_par_id', $user->id)->latest('active_le')->limit(3)->get()
                : collect(),
        ]);
    }

    /**
     * @return array{activations_du_jour: int, mes_activations_du_jour: int, cartes_actives: int}
     */
    private function indicateursCartes(int $userId): array
    {
        $resultat = Carte::query()
            ->selectRaw('SUM(active_le >= ?) AS activations_du_jour', [today()])
            ->selectRaw('SUM(active_par_id = ? AND active_le >= ?) AS mes_activations_du_jour', [$userId, today()])
            ->selectRaw('SUM(statut = ? AND expire_le > ?) AS cartes_actives', [StatutCarte::Active->value, now()])
            ->first();

        return [
            'activations_du_jour' => (int) $resultat->activations_du_jour,
            'mes_activations_du_jour' => (int) $resultat->mes_activations_du_jour,
            'cartes_actives' => (int) $resultat->cartes_actives,
        ];
    }

    /**
     * Cartes actives arrivant à échéance (paliers des alertes : 1, 2, 3 mois)
     * et les plus proches de l'échéance.
     *
     * @return array{paliers: array<int, int>, prochaines: Collection<int, Carte>}
     */
    private function expirationsProches(): array
    {
        $actives = fn () => Carte::query()->where('statut', StatutCarte::Active)->where('expire_le', '>', now());

        $resultat = $actives()
            ->selectRaw('SUM(expire_le <= ?) AS un_mois', [now()->addMonth()])
            ->selectRaw('SUM(expire_le <= ?) AS deux_mois', [now()->addMonths(2)])
            ->selectRaw('SUM(expire_le <= ?) AS trois_mois', [now()->addMonths(3)])
            ->first();

        return [
            'paliers' => [1 => (int) $resultat->un_mois, 2 => (int) $resultat->deux_mois, 3 => (int) $resultat->trois_mois],
            'prochaines' => $actives()->with('titulaire')->where('expire_le', '<=', now()->addMonths(3))
                ->orderBy('expire_le')->limit(5)->get(),
        ];
    }

    /**
     * @return array{passages_du_jour: int, passages_du_mois: int, partenaires_actifs: int}
     */
    private function indicateursTransactions(): array
    {
        $resultat = Transaction::query()
            ->selectRaw('SUM(validee_le >= ?) AS passages_du_jour', [today()])
            ->selectRaw('SUM(validee_le >= ?) AS passages_du_mois', [today()->startOfMonth()])
            ->first();

        return [
            'passages_du_jour' => (int) $resultat->passages_du_jour,
            'passages_du_mois' => (int) $resultat->passages_du_mois,
            'partenaires_actifs' => Partenaire::query()->where('statut', StatutPartenaire::Actif)->count(),
        ];
    }
}
