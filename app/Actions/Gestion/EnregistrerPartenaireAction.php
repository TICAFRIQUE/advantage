<?php

namespace App\Actions\Gestion;

use App\Enums\StatutPartenaire;
use App\Models\HistoriqueTauxPartenaire;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crée ou modifie un partenaire. Toute évolution du taux de réduction est
 * tracée dans historique_taux_partenaires (ancien, nouveau, auteur, date) ;
 * les transactions passées gardent leur taux figé.
 */
class EnregistrerPartenaireAction
{
    /**
     * @param  array{nom: string, secteur?: ?string, localisation?: ?string, contact?: ?string, taux_reduction: string|float}  $donnees
     */
    public function creer(array $donnees, User $auteur): Partenaire
    {
        return DB::transaction(function () use ($donnees, $auteur): Partenaire {
            $partenaire = Partenaire::create($donnees + ['statut' => StatutPartenaire::Actif]);

            $this->tracerTaux($partenaire, null, $auteur);

            return $partenaire;
        });
    }

    /**
     * @param  array{nom: string, secteur?: ?string, localisation?: ?string, contact?: ?string, taux_reduction: string|float}  $donnees
     */
    public function modifier(Partenaire $partenaire, array $donnees, User $auteur): Partenaire
    {
        return DB::transaction(function () use ($partenaire, $donnees, $auteur): Partenaire {
            $partenaire = Partenaire::query()->lockForUpdate()->findOrFail($partenaire->id);
            $ancienTaux = (string) $partenaire->taux_reduction;

            $partenaire->fill($donnees);

            if (! $partenaire->isDirty()) {
                return $partenaire;
            }

            $partenaire->save();

            if ((float) $ancienTaux !== (float) $partenaire->taux_reduction) {
                $this->tracerTaux($partenaire, $ancienTaux, $auteur);
            }

            return $partenaire;
        });
    }

    public function changerStatut(Partenaire $partenaire, StatutPartenaire $statut): Partenaire
    {
        $partenaire->update(['statut' => $statut]);

        return $partenaire;
    }

    private function tracerTaux(Partenaire $partenaire, ?string $ancien, User $auteur): void
    {
        HistoriqueTauxPartenaire::create([
            'partenaire_id' => $partenaire->id,
            'ancien_taux' => $ancien,
            'nouveau_taux' => $partenaire->taux_reduction,
            'modifie_par_id' => $auteur->id,
            'modifie_le' => now(),
        ]);
    }
}
