<?php

namespace App\Services\Listes;

use App\Enums\StatutPartenaire;
use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Partenaires › Liste des partenaires (filtres : partenaire, statut).
 *
 * @extends Liste<Partenaire>
 */
class ListePartenaires extends Liste
{
    public function cle(): string
    {
        return 'partenaires';
    }

    public function titre(): string
    {
        return 'Liste des partenaires';
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        // select() AVANT withCount() : sinon les colonnes de comptage sont écrasées.
        return Partenaire::query()->select('partenaires.*')->withCount(['operateurs', 'transactions'])
            ->when($f['partenaire_id'] ?? null, fn ($q, int|string $id) => $q->whereKey($id))
            ->when($f['statut'] ?? null, fn ($q, string $statut) => $q->where('statut', $statut));
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');

        $query->where(fn ($q) => $q
            ->where('nom', 'like', "%{$texte}%")
            ->orWhere('secteur', 'like', "%{$texte}%")
            ->orWhere('localisation', 'like', "%{$texte}%"));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->orderBy('nom');
    }

    public function colonnes(): array
    {
        return ['Nom', 'Secteur', 'Localisation', 'Contact', 'Remise', 'Statut', 'Utilisateurs', 'Passages'];
    }

    /**
     * @param  Partenaire  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->nom,
            $modele->secteur,
            $modele->localisation,
            $modele->contact,
            $modele->tauxFormate(),
            $modele->statut->libelle(),
            (int) $modele->operateurs_count,
            (int) $modele->transactions_count,
        ];
    }

    public function filtresLisibles(): array
    {
        return array_filter([
            'Partenaire' => filled($this->filtres['partenaire_id'] ?? null) ? Partenaire::find($this->filtres['partenaire_id'])?->nom : null,
            'Statut' => ($statut = StatutPartenaire::tryFrom((string) ($this->filtres['statut'] ?? ''))) ? $statut->libelle() : null,
        ]);
    }
}
