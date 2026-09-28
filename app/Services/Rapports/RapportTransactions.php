<?php

namespace App\Services\Rapports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rapport des transactions (passages chez les partenaires) : une seule
 * définition des filtres pour indicateurs, liste et exports.
 *
 * Filtres : du / au (date de validation), partenaire_id, valide_par_id.
 */
class RapportTransactions
{
    /**
     * @param  array{du?: ?string, au?: ?string, partenaire_id?: ?int, valide_par_id?: ?int}  $filtres
     */
    public function __construct(private array $filtres) {}

    /**
     * @return Builder<Transaction>
     */
    public function requete(): Builder
    {
        $f = $this->filtres;

        return Transaction::query()
            ->when($f['du'] ?? null, fn (Builder $q, string $du) => $q->where('transactions.validee_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, string $au) => $q->where('transactions.validee_le', '<=', $au.' 23:59:59'))
            ->when($f['partenaire_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('transactions.partenaire_id', $id))
            ->when($f['valide_par_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('transactions.valide_par_id', $id));
    }

    /**
     * @return array{passages: int, cartes_distinctes: int, partenaires_distincts: int, taux_moyen: string}
     */
    public function indicateurs(): array
    {
        $resultat = $this->requete()->toBase()
            ->selectRaw('COUNT(*) AS passages')
            ->selectRaw('COUNT(DISTINCT transactions.carte_id) AS cartes_distinctes')
            ->selectRaw('COUNT(DISTINCT transactions.partenaire_id) AS partenaires_distincts')
            ->selectRaw('AVG(transactions.taux_applique) AS taux_moyen')
            ->first();

        return [
            'passages' => (int) $resultat->passages,
            'cartes_distinctes' => (int) $resultat->cartes_distinctes,
            'partenaires_distincts' => (int) $resultat->partenaires_distincts,
            'taux_moyen' => $resultat->taux_moyen === null ? '—' : rtrim(rtrim(number_format((float) $resultat->taux_moyen, 2, ',', ''), '0'), ',').' %',
        ];
    }

    /**
     * Passages par partenaire (les 10 premiers), sur le même périmètre.
     *
     * @return Collection<int, object{partenaire: string, total: int}>
     */
    public function parPartenaire(): Collection
    {
        return $this->requete()->toBase()
            ->join('partenaires', 'partenaires.id', '=', 'transactions.partenaire_id')
            ->selectRaw('partenaires.nom AS partenaire, COUNT(*) AS total')
            ->groupBy('partenaires.id', 'partenaires.nom')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }
}
