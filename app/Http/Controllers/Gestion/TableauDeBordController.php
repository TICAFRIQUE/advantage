<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Carte;
use App\Services\EcheancesCartes;
use App\Services\StatistiquesTableauDeBord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vue d'ensemble du back-office : seuls les indicateurs que l'utilisateur a
 * le droit de voir sont affichés. Indicateurs globaux en cache
 * (StatistiquesTableauDeBord) ; « mes activations » calculé à la volée
 * (index cartes(active_par_id, active_le)).
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $globales = StatistiquesTableauDeBord::globales();

        return view('gestion.tableau-de-bord', [
            'cartes' => $user->can(Permission::VoirCartes->value) ? [
                'activations_du_jour' => $globales['activations_du_jour'],
                'mes_activations_du_jour' => Carte::query()->where('active_par_id', $user->id)->where('active_le', '>=', today())->count(),
                'cartes_actives' => $globales['cartes_actives'],
            ] : null,
            'transactions' => $user->can(Permission::VoirRapportTransactions->value) ? [
                'passages_du_jour' => $globales['passages_du_jour'],
                'passages_du_mois' => $globales['passages_du_mois'],
                'partenaires_actifs' => $globales['partenaires_actifs'],
            ] : null,
            'expirations' => $user->can(Permission::VoirCartes->value) ? EcheancesCartes::resume() : null,
            'dernieres' => $user->can(Permission::ActiverCarte->value)
                ? Carte::query()->with('titulaire')->where('active_par_id', $user->id)->latest('active_le')->limit(3)->get()
                : collect(),
        ]);
    }
}
