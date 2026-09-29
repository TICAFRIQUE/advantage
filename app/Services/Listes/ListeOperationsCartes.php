<?php

namespace App\Services\Listes;

use App\Enums\TypeOperationCarte;
use App\Models\OperationCarte;
use App\Models\User;
use App\Services\Rapports\RapportCartes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cartes › Rapport des cartes : historique permanent des opérations
 * (filtres du rapport : RapportCartes).
 *
 * @extends Liste<OperationCarte>
 */
class ListeOperationsCartes extends Liste
{
    public function cle(): string
    {
        return 'operations-cartes';
    }

    public function titre(): string
    {
        return 'Rapport des cartes';
    }

    public function requete(): Builder
    {
        return (new RapportCartes($this->filtres, $this->utilisateur))->requete()
            ->with(['carte.titulaire', 'effectueePar.roles'])
            ->select('operations_cartes.*');
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');
        $chiffres = preg_replace('/\D/', '', $recherche);

        $query->whereHas('carte', fn ($c) => $c->withTrashed()->where(fn ($c) => $c
            ->when($chiffres !== '', fn ($c) => $c->where('numero_carte', 'like', $chiffres.'%'))
            ->orWhereHas('titulaire', fn ($t) => $t
                ->where('nom', 'like', "%{$texte}%")
                ->orWhere('prenom', 'like', "%{$texte}%")
                // Sans chiffre, un LIKE '%%' ramènerait toutes les cartes.
                ->when($chiffres !== '', fn ($t) => $t->orWhere('telephone', 'like', "%{$chiffres}%")))));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('operations_cartes.effectuee_le')->latest('operations_cartes.id');
    }

    public function colonnes(): array
    {
        return ['Date', 'Opération', 'Carte', 'Titulaire', 'Téléphone', 'Effectuée par', 'Motif'];
    }

    /**
     * @param  OperationCarte  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->effectuee_le->format('d/m/Y H:i'),
            $modele->type->libelle(),
            $modele->carte->numeroFormate(),
            $modele->carte->titulaire->nomComplet(),
            $modele->carte->titulaire->telephoneFormate(),
            $modele->libelleAuteur(),
            $modele->motif,
        ];
    }

    public function filtresLisibles(): array
    {
        $f = $this->filtres;

        return array_filter([
            'Du' => $f['du'] ?? null,
            'Au' => $f['au'] ?? null,
            'Carte' => $f['carte'] ?? null,
            'Opération' => ($type = TypeOperationCarte::tryFrom((string) ($f['type'] ?? ''))) ? $type->libelle() : null,
            'Effectuée par' => filled($f['agent_id'] ?? null) ? User::withTrashed()->find($f['agent_id'])?->nom : null,
            'Périmètre' => filter_var($f['mes_operations'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Mes opérations' : null,
        ]);
    }
}
