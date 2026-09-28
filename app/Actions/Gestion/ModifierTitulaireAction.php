<?php

namespace App\Actions\Gestion;

use App\Enums\StatutDemandeOtp;
use App\Enums\TypeSms;
use App\Exceptions\ActionCarteImpossibleException;
use App\Models\DemandeOtp;
use App\Models\Titulaire;
use App\Services\Sms\EnvoiSms;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Modifie la fiche d'un titulaire. Chaque changement est audité (avant /
 * après) par TitulaireObserver et tracé (modifie_par_id).
 *
 * Le téléphone est la donnée la plus sensible : les codes OTP y sont
 * envoyés. Son changement (permission dédiée, PIN confirmé en amont) :
 * - informe l'ancien numéro par SMS (alerte en cas de fraude) ;
 * - annule les codes OTP en attente, envoyés à l'ancien numéro.
 */
class ModifierTitulaireAction
{
    public function __construct(private EnvoiSms $envoiSms) {}

    /**
     * @param  array{nom?: string, prenom?: string}  $identite
     */
    public function identite(Titulaire $titulaire, array $identite): Titulaire
    {
        $titulaire->fill($identite);

        if ($titulaire->isDirty()) {
            $titulaire->save();
        }

        return $titulaire;
    }

    /**
     * @param  string  $telephone  numéro au format E.164
     *
     * @throws ActionCarteImpossibleException
     */
    public function telephone(Titulaire $titulaire, string $telephone): Titulaire
    {
        if ($telephone === $titulaire->telephone) {
            return $titulaire;
        }

        if (Titulaire::query()->where('telephone', $telephone)->whereKeyNot($titulaire->id)->exists()) {
            throw new ActionCarteImpossibleException('Ce numéro est déjà associé à un autre titulaire.');
        }

        $ancien = $titulaire->telephone;

        try {
            DB::transaction(function () use ($titulaire, $telephone): void {
                $titulaire->update(['telephone' => $telephone]);

                DemandeOtp::query()
                    ->whereIn('carte_id', $titulaire->cartes()->select('id'))
                    ->where('statut', StatutDemandeOtp::EnAttente)
                    ->update(['statut' => StatutDemandeOtp::Expiree, 'updated_at' => now()]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ActionCarteImpossibleException('Ce numéro est déjà associé à un autre titulaire.');
        }

        $this->envoiSms->envoyer(
            $ancien,
            'ADVANTAGE : le numéro de téléphone associé à votre carte a été modifié. Si vous n\'êtes pas à l\'origine de ce changement, contactez une agence ADVANTAGE.',
            TypeSms::Information,
        );

        return $titulaire;
    }
}
