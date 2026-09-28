<?php

namespace App\Services\Rapports;

use App\Enums\StatutCarte;
use App\Models\Carte;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rapport des cartes : UNE seule définition des filtres, partagée par les
 * indicateurs, la liste (Yajra) et les exports — un export ne peut jamais
 * contenir plus que ce que l'écran affiche.
 *
 * Filtres : du / au (date d'activation), agent_id, statut (effectif),
 * mes_activations.
 */
class RapportCartes
{
    /**
     * @param  array{du?: ?string, au?: ?string, agent_id?: ?int, statut?: ?string, mes_activations?: ?bool}  $filtres
     */
    public function __construct(private array $filtres, private User $utilisateur) {}

    /**
     * @return Builder<Carte>
     */
    public function requete(): Builder
    {
        $f = $this->filtres;

        return Carte::query()
            ->when($f['du'] ?? null, fn (Builder $q, string $du) => $q->where('cartes.active_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, string $au) => $q->where('cartes.active_le', '<=', $au.' 23:59:59'))
            ->when($f['agent_id'] ?? null, fn (Builder $q, int|string $agent) => $q->where('cartes.active_par_id', $agent))
            ->when($f['mes_activations'] ?? false, fn (Builder $q) => $q->where('cartes.active_par_id', $this->utilisateur->id))
            ->when($f['statut'] ?? null, fn (Builder $q, string $statut) => $q->statutEffectif(StatutCarte::from($statut)));
    }

    /**
     * Indicateurs calculés en une requête SQL agrégée (statut effectif).
     *
     * @return array{total: int, actives: int, suspendues: int, revoquees: int, expirees: int, expirent_sous_30_jours: int}
     */
    public function indicateurs(): array
    {
        $maintenant = now();

        $resultat = $this->requete()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(statut = ? AND expire_le > ?) AS actives', [StatutCarte::Active->value, $maintenant])
            ->selectRaw('SUM(statut = ?) AS suspendues', [StatutCarte::Suspendue->value])
            ->selectRaw('SUM(statut = ?) AS revoquees', [StatutCarte::Revoquee->value])
            ->selectRaw('SUM(statut = ? OR (statut = ? AND expire_le <= ?)) AS expirees', [StatutCarte::Expiree->value, StatutCarte::Active->value, $maintenant])
            ->selectRaw('SUM(statut = ? AND expire_le > ? AND expire_le <= ?) AS expirent_sous_30_jours', [StatutCarte::Active->value, $maintenant, $maintenant->copy()->addDays(30)])
            ->first();

        return collect((array) $resultat)->map(fn ($valeur) => (int) $valeur)->all();
    }

    /**
     * Activations par agent (les 10 premiers), sur le même périmètre.
     *
     * @return Collection<int, object{agent: string, total: int}>
     */
    public function parAgent(): Collection
    {
        return $this->requete()->toBase()
            ->join('users', 'users.id', '=', 'cartes.active_par_id')
            ->selectRaw('users.nom AS agent, COUNT(*) AS total')
            ->groupBy('users.id', 'users.nom')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }
}
