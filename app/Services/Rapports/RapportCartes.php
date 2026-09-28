<?php

namespace App\Services\Rapports;

use App\Enums\TypeOperationCarte;
use App\Models\OperationCarte;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rapport des cartes = historique des opérations (activations, suspensions,
 * réactivations, révocations, expirations, modifications du titulaire).
 *
 * UNE seule définition des filtres, partagée par les indicateurs, la liste
 * (Yajra) et les exports — un export ne peut jamais contenir plus que ce que
 * l'écran affiche.
 *
 * Filtres : du / au (date de l'opération), type, agent_id (auteur),
 * mes_operations.
 */
class RapportCartes
{
    /**
     * @param  array{du?: ?string, au?: ?string, type?: ?string, agent_id?: ?int, mes_operations?: ?bool}  $filtres
     */
    public function __construct(private array $filtres, private User $utilisateur) {}

    /**
     * @return Builder<OperationCarte>
     */
    public function requete(): Builder
    {
        $f = $this->filtres;

        return OperationCarte::query()
            ->when($f['du'] ?? null, fn (Builder $q, string $du) => $q->where('operations_cartes.effectuee_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, string $au) => $q->where('operations_cartes.effectuee_le', '<=', $au.' 23:59:59'))
            ->when($f['type'] ?? null, fn (Builder $q, string $type) => $q->where('operations_cartes.type', $type))
            ->when($f['agent_id'] ?? null, fn (Builder $q, int|string $agent) => $q->where('operations_cartes.effectuee_par_id', $agent))
            ->when($f['mes_operations'] ?? false, fn (Builder $q) => $q->where('operations_cartes.effectuee_par_id', $this->utilisateur->id));
    }

    /**
     * Nombre d'opérations par type sur le périmètre, en une requête agrégée.
     *
     * @return array<string, int> total + une entrée par valeur de TypeOperationCarte
     */
    public function indicateurs(): array
    {
        $parType = $this->requete()->toBase()
            ->selectRaw('operations_cartes.type, COUNT(*) AS total')
            ->groupBy('operations_cartes.type')
            ->pluck('total', 'type')
            ->map(fn ($total) => (int) $total);

        return ['total' => $parType->sum()]
            + collect(TypeOperationCarte::valeurs())->mapWithKeys(fn (string $type) => [$type => $parType->get($type, 0)])->all();
    }
}
