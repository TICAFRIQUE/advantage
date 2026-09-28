<?php

namespace App\Actions\Agent;

use App\Enums\StatutCarte;
use App\Exceptions\ActivationImpossibleException;
use App\Models\Carte;
use App\Models\Titulaire;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Active une carte physique (numéro imprimé hors application) pour un
 * titulaire identifié par son téléphone.
 *
 * Concurrence :
 * - deux agents activant le même numéro : la contrainte UNIQUE sur
 *   `cartes.numero_carte` fait échouer le second, converti en message métier ;
 * - deux agents créant le même titulaire : la contrainte UNIQUE sur
 *   `titulaires.telephone` fait échouer le second, qui relit alors la ligne ;
 * - le titulaire est verrouillé (FOR UPDATE) pendant la vérification
 *   « une seule carte active » pour éviter deux activations parallèles.
 */
class ActiverCarteAction
{
    /**
     * @param  array{numero_carte: string, nom: string, prenom: string, telephone: string}  $donnees
     *
     * @throws ActivationImpossibleException
     */
    public function __invoke(array $donnees, User $agent): Carte
    {
        if (Carte::withTrashed()->where('numero_carte', $donnees['numero_carte'])->exists()) {
            throw ActivationImpossibleException::carteDejaActivee($donnees['numero_carte']);
        }

        try {
            return DB::transaction(function () use ($donnees, $agent): Carte {
                $titulaire = $this->titulaireVerrouille($donnees);

                $this->verifierAucuneCarteActive($titulaire);

                return Carte::create([
                    'numero_carte' => $donnees['numero_carte'],
                    'titulaire_id' => $titulaire->id,
                    'active_par_id' => $agent->id,
                    'active_le' => now(),
                    'statut' => StatutCarte::Active,
                ]);
            }, attempts: 3); // rejoue en cas d'interblocage MySQL (verrous de plage)
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'numero_carte')) {
                throw ActivationImpossibleException::carteDejaActivee($donnees['numero_carte']);
            }

            throw $exception;
        }
    }

    /**
     * Retrouve (et verrouille) le titulaire par téléphone, ou le crée.
     * Les nom et prénoms saisis mettent à jour la fiche existante (audités).
     *
     * @param  array{nom: string, prenom: string, telephone: string}  $donnees
     */
    private function titulaireVerrouille(array $donnees): Titulaire
    {
        $titulaire = Titulaire::query()->parTelephone($donnees['telephone'])->lockForUpdate()->first();

        if ($titulaire === null) {
            try {
                // MySQL : un doublon n'annule que l'instruction fautive, la
                // transaction englobante reste utilisable pour relire la ligne.
                $titulaire = Titulaire::create([
                    'nom' => $donnees['nom'],
                    'prenom' => $donnees['prenom'],
                    'telephone' => $donnees['telephone'],
                ]);
            } catch (UniqueConstraintViolationException) {
                // Créé entre-temps par un autre agent : on reprend sa fiche.
                $titulaire = Titulaire::query()->parTelephone($donnees['telephone'])->lockForUpdate()->firstOrFail();
            }
        }

        $titulaire->fill(['nom' => $donnees['nom'], 'prenom' => $donnees['prenom']]);

        if ($titulaire->isDirty()) {
            $titulaire->save();
        }

        return $titulaire;
    }

    /**
     * Une seule carte en circulation par titulaire. Une carte « active » dont
     * la date est échue est basculée en « expirée » au passage.
     */
    private function verifierAucuneCarteActive(Titulaire $titulaire): void
    {
        $cartesActives = $titulaire->cartes()
            ->whereIn('statut', [StatutCarte::Active, StatutCarte::Suspendue])
            ->get();

        foreach ($cartesActives as $carte) {
            if ($carte->statutEffectif() === StatutCarte::Expiree) {
                $carte->update(['statut' => StatutCarte::Expiree, 'motif_statut' => 'Date de validité dépassée']);

                continue;
            }

            throw ActivationImpossibleException::titulaireDejaEquipe($carte->numeroFormate());
        }
    }
}
