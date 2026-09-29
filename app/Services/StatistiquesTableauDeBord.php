<?php

namespace App\Services;

use App\Enums\StatutCarte;
use App\Enums\StatutPartenaire;
use App\Models\Carte;
use App\Models\Partenaire;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

/**
 * Indicateurs globaux du tableau de bord (communs à tous les utilisateurs) :
 * requêtes agrégées mises en cache 5 minutes, invalidées par les observers
 * (cartes, transactions, partenaires). Les indicateurs propres à un
 * utilisateur (« mes activations ») restent calculés à la volée.
 */
class StatistiquesTableauDeBord
{
    public const CLE_CACHE = 'statistiques-tableau-de-bord';

    /**
     * @return array{activations_du_jour: int, cartes_actives: int, passages_du_jour: int, passages_du_mois: int, partenaires_actifs: int}
     */
    public static function globales(): array
    {
        return Cache::remember(self::CLE_CACHE, now()->addMinutes(5), function (): array {
            $cartes = Carte::query()
                ->selectRaw('SUM(active_le >= ?) AS activations_du_jour', [today()])
                ->selectRaw('SUM(statut = ? AND expire_le > ?) AS cartes_actives', [StatutCarte::Active->value, now()])
                ->first();

            $transactions = Transaction::query()
                ->selectRaw('SUM(validee_le >= ?) AS passages_du_jour', [today()])
                ->selectRaw('SUM(validee_le >= ?) AS passages_du_mois', [today()->startOfMonth()])
                ->first();

            return [
                'activations_du_jour' => (int) $cartes->activations_du_jour,
                'cartes_actives' => (int) $cartes->cartes_actives,
                'passages_du_jour' => (int) $transactions->passages_du_jour,
                'passages_du_mois' => (int) $transactions->passages_du_mois,
                'partenaires_actifs' => Partenaire::query()->where('statut', StatutPartenaire::Actif)->count(),
            ];
        });
    }

    public static function oublier(): void
    {
        Cache::forget(self::CLE_CACHE);
    }
}
