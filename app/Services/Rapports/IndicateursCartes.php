<?php

namespace App\Services\Rapports;

use App\Enums\StatutCarte;
use App\Models\Carte;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indicateurs du parc de cartes, sur le statut effectif (une carte « active »
 * dont la date est échue compte comme expirée), en une requête agrégée.
 */
class IndicateursCartes
{
    /**
     * @param  Builder<Carte>|null  $requete  périmètre (toutes les cartes par défaut)
     * @return array{total: int, actives: int, expirent_sous_30_jours: int, suspendues: int, revoquees: int, expirees: int}
     */
    public static function calculer(?Builder $requete = null): array
    {
        $maintenant = now();

        $resultat = ($requete ?? Carte::query())->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(statut = ? AND expire_le > ?) AS actives', [StatutCarte::Active->value, $maintenant])
            ->selectRaw('SUM(statut = ? AND expire_le > ? AND expire_le <= ?) AS expirent_sous_30_jours', [StatutCarte::Active->value, $maintenant, $maintenant->copy()->addDays(30)])
            ->selectRaw('SUM(statut = ?) AS suspendues', [StatutCarte::Suspendue->value])
            ->selectRaw('SUM(statut = ?) AS revoquees', [StatutCarte::Revoquee->value])
            ->selectRaw('SUM(statut = ? OR (statut = ? AND expire_le <= ?)) AS expirees', [StatutCarte::Expiree->value, StatutCarte::Active->value, $maintenant])
            ->first();

        return collect((array) $resultat)->map(fn ($valeur) => (int) $valeur)->all();
    }
}
