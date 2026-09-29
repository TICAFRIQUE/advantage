<?php

namespace App\Actions\Gestion;

use App\Actions\Comptes\GererCompteAction;
use App\Enums\StatutDemandeOtp;
use App\Enums\StatutPartenaire;
use App\Models\DemandeOtp;
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
     * @param  array{nom: string, secteur?: ?string, localisation?: ?string, contact?: ?string, responsable?: ?string, email?: ?string, taux_reduction: string|float}  $donnees
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
     * @param  array{nom: string, secteur?: ?string, localisation?: ?string, contact?: ?string, responsable?: ?string, email?: ?string, taux_reduction: string|float}  $donnees
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

    /**
     * Archivage (suppression douce) du partenaire et de ses utilisateurs : plus
     * aucune connexion ni transaction possible, mais les transactions passées
     * gardent le nom du partenaire. Les codes en attente sont expirés.
     */
    public function supprimer(Partenaire $partenaire, User $auteur): void
    {
        DB::transaction(function () use ($partenaire, $auteur): void {
            $partenaire = Partenaire::query()->lockForUpdate()->findOrFail($partenaire->id);
            $comptes = app(GererCompteAction::class);

            $partenaire->operateurs()->get()->each(fn (User $operateur) => $comptes->archiver($operateur, $auteur));

            DemandeOtp::query()
                ->where('partenaire_id', $partenaire->id)
                ->where('statut', StatutDemandeOtp::EnAttente)
                ->update(['statut' => StatutDemandeOtp::Expiree, 'updated_at' => now()]);

            Partenaire::query()->whereKey($partenaire->id)->update(['supprime_par_id' => $auteur->id]);
            $partenaire->delete();
        });
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
