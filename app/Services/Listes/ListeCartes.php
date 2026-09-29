<?php

namespace App\Services\Listes;

use App\Enums\StatutCarte;
use App\Models\Carte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cartes › Liste des cartes (filtres : recherche, statut effectif, mes activations).
 *
 * @extends Liste<Carte>
 */
class ListeCartes extends Liste
{
    public function cle(): string
    {
        return 'cartes';
    }

    public function titre(): string
    {
        return 'Liste des cartes';
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        return Carte::query()
            ->with(['titulaire', 'activePar.roles'])
            ->when($f['recherche'] ?? null, fn (Builder $query, string $recherche) => $this->rechercher($query, $recherche))
            ->when($f['statut'] ?? null, fn (Builder $query, string $statut) => $query->statutEffectif(StatutCarte::from($statut)))
            ->when($this->mesActivations(), fn (Builder $query) => $query->where('active_par_id', $this->utilisateur->id))
            // Échéance proche : cartes actives expirant sous N mois (paliers des alertes).
            ->when($f['expire_dans'] ?? null, fn (Builder $query, int|string $mois) => $query
                ->where('statut', StatutCarte::Active)
                ->where('expire_le', '>', now())
                ->where('expire_le', '<=', now()->addMonths((int) $mois)));
    }

    /**
     * Numéro (préfixe) ou téléphone si la saisie n'est faite que de chiffres,
     * sinon nom / prénoms du titulaire.
     */
    public function rechercher(Builder $query, string $recherche): void
    {
        $chiffres = preg_replace('/\D/', '', $recherche);
        $texte = addcslashes(trim($recherche), '%_\\');

        if ($chiffres !== '' && $chiffres === preg_replace('/\s/', '', $recherche)) {
            $query->where(fn (Builder $q) => $q
                ->where('numero_carte', 'like', $chiffres.'%')
                ->orWhereHas('titulaire', fn (Builder $t) => $t->where('telephone', 'like', '%'.$chiffres.'%')));

            return;
        }

        $query->whereHas('titulaire', fn (Builder $t) => $t
            ->where('nom', 'like', '%'.$texte.'%')
            ->orWhere('prenom', 'like', '%'.$texte.'%'));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('active_le')->latest('id');
    }

    public function colonnes(): array
    {
        return ['Numéro', 'Titulaire', 'Téléphone', 'Statut', 'Activée le', 'Activée par', 'Expire le'];
    }

    /**
     * @param  Carte  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->numeroFormate(),
            $modele->titulaire->nomComplet(),
            $modele->titulaire->telephoneFormate(),
            $modele->statutEffectif()->libelle(),
            $modele->active_le?->format('d/m/Y H:i'),
            $modele->activePar?->libelleActeur(),
            $modele->expire_le?->format('d/m/Y'),
        ];
    }

    public function filtresLisibles(): array
    {
        return array_filter([
            'Recherche' => $this->filtres['recherche'] ?? null,
            'Statut' => ($statut = StatutCarte::tryFrom((string) ($this->filtres['statut'] ?? ''))) ? $statut->libelle() : null,
            'Périmètre' => $this->mesActivations() ? 'Mes activations' : null,
            'Échéance' => filled($this->filtres['expire_dans'] ?? null) ? 'sous '.$this->filtres['expire_dans'].' mois' : null,
        ]);
    }

    private function mesActivations(): bool
    {
        return filter_var($this->filtres['mes_activations'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
