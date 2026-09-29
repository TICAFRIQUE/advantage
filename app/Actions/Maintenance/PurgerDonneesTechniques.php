<?php

namespace App\Actions\Maintenance;

use App\Enums\StatutDemandeOtp;
use App\Enums\StatutLivraison;
use App\Models\DemandeOtp;
use App\Models\MessageSms;
use App\Services\JournaliserAudit;

/**
 * Rétention des tables techniques à forte croissance (plateforme.retention) :
 * - demandes de code terminées (utilisées, expirées, bloquées) au-delà de la
 *   rétention, SAUF celles liées à une transaction (preuve de la validation) ;
 * - SMS envoyés ou en échec au-delà de la rétention (jamais ceux en attente).
 * Le journal d'audit et l'historique des cartes ne sont jamais touchés ici.
 */
class PurgerDonneesTechniques
{
    /**
     * @return array{demandes_otp: int, messages_sms: int}
     */
    public function __invoke(): array
    {
        $limiteOtp = now()->subDays((int) config('plateforme.retention.demandes_otp_jours'));
        $limiteSms = now()->subDays((int) config('plateforme.retention.messages_sms_jours'));

        $resultat = [
            'demandes_otp' => DemandeOtp::query()
                ->where('created_at', '<', $limiteOtp)
                ->where('statut', '!=', StatutDemandeOtp::EnAttente)
                ->whereDoesntHave('transaction')
                ->delete(),
            'messages_sms' => MessageSms::query()
                ->where('created_at', '<', $limiteSms)
                ->whereIn('statut', [StatutLivraison::Envoyee, StatutLivraison::Echec])
                ->delete(),
        ];

        if (array_sum($resultat) > 0) {
            JournaliserAudit::enregistrer('donnees.purgees', donnees: $resultat);
        }

        return $resultat;
    }
}
