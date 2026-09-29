<?php

namespace App\Services\Listes;

use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Rapports\RapportTransactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Partenaires › Rapport des transactions (filtres : RapportTransactions).
 *
 * @extends Liste<Transaction>
 */
class ListeTransactions extends Liste
{
    public function cle(): string
    {
        return 'transactions';
    }

    public function titre(): string
    {
        return 'Rapport des transactions';
    }

    public function requete(): Builder
    {
        return (new RapportTransactions($this->filtres))->requete()
            ->with(['partenaire', 'carte.titulaire', 'validePar.roles'])
            ->select('transactions.*');
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');
        $chiffres = preg_replace('/\D/', '', $recherche);

        // Chaque niveau est regroupé entre parenthèses : un OR non groupé dans
        // un whereHas « fuirait » hors de la condition de jointure.
        $query->where(fn ($q) => $q
            ->whereHas('partenaire', fn ($p) => $p->where('nom', 'like', "%{$texte}%"))
            ->orWhereHas('carte', fn ($c) => $c->where(fn ($c) => $c
                ->when($chiffres !== '', fn ($c) => $c->where('numero_carte', 'like', $chiffres.'%'))
                ->orWhereHas('titulaire', fn ($t) => $t->where(fn ($t) => $t
                    ->where('nom', 'like', "%{$texte}%")
                    ->orWhere('prenom', 'like', "%{$texte}%"))))));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('transactions.validee_le')->latest('transactions.id');
    }

    public function colonnes(): array
    {
        return ['Date', 'Partenaire', 'Carte', 'Titulaire', 'Remise', 'Validée par'];
    }

    /**
     * @param  Transaction  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->validee_le->format('d/m/Y H:i'),
            $modele->partenaire->nom,
            $modele->carte->numeroFormate(),
            $modele->carte->titulaire->nomComplet(),
            rtrim(rtrim((string) $modele->taux_applique, '0'), '.').' %',
            $modele->validePar?->libelleActeur(),
        ];
    }

    public function filtresLisibles(): array
    {
        $f = $this->filtres;

        return array_filter([
            'Du' => $f['du'] ?? null,
            'Au' => $f['au'] ?? null,
            'Carte' => $f['carte'] ?? null,
            'Partenaire' => filled($f['partenaire_id'] ?? null) ? Partenaire::withTrashed()->find($f['partenaire_id'])?->nom : null,
            'Validée par' => filled($f['valide_par_id'] ?? null) ? User::withTrashed()->find($f['valide_par_id'])?->nom : null,
        ]);
    }
}
