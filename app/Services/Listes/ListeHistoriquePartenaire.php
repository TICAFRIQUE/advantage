<?php

namespace App\Services\Listes;

use App\Models\Partenaire;
use App\Models\Transaction;
use App\Services\PartenaireCourant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Espace partenaire › Historique : transactions du partenaire courant
 * uniquement (filtres : période).
 *
 * @extends Liste<Transaction>
 */
class ListeHistoriquePartenaire extends Liste
{
    public function cle(): string
    {
        return 'historique';
    }

    public function titre(): string
    {
        return 'Historique des transactions — '.($this->partenaire()?->nom ?? '');
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        return Transaction::query()
            ->with(['carte.titulaire', 'validePar.roles'])
            ->where('transactions.partenaire_id', $this->partenaire()?->id)
            ->when($f['du'] ?? null, fn (Builder $q, string $du) => $q->where('validee_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, string $au) => $q->where('validee_le', '<=', $au.' 23:59:59'))
            ->select('transactions.*');
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');

        $query->whereHas('carte', fn (Builder $c) => $c->where(fn (Builder $c) => $c
            ->where('numero_carte', 'like', preg_replace('/\s+/', '', $texte).'%')
            ->orWhereHas('titulaire', fn (Builder $t) => $t->where(fn (Builder $t) => $t
                ->where('nom', 'like', "%{$texte}%")
                ->orWhere('prenom', 'like', "%{$texte}%")))));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('transactions.validee_le')->latest('transactions.id');
    }

    public function colonnes(): array
    {
        return ['Date', 'Carte', 'Titulaire', 'Remise', 'Validée par'];
    }

    /**
     * @param  Transaction  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->validee_le->format('d/m/Y H:i'),
            $modele->carte->numeroFormate(),
            $modele->carte->titulaire->nomComplet(),
            rtrim(rtrim((string) $modele->taux_applique, '0'), '.').' %',
            $modele->validePar?->libelleActeur(),
        ];
    }

    public function filtresLisibles(): array
    {
        return array_filter([
            'Du' => $this->filtres['du'] ?? null,
            'Au' => $this->filtres['au'] ?? null,
        ]);
    }

    private function partenaire(): ?Partenaire
    {
        return PartenaireCourant::pour($this->utilisateur);
    }
}
