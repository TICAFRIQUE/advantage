<?php

namespace App\Actions\Maintenance;

use App\Enums\StatutLivraison;
use App\Models\MessageSms;
use App\Services\JournaliserAudit;

/**
 * Purge hebdomadaire de l'historique des SMS : seuls les N plus récents sont
 * conservés (plateforme.retention.messages_sms_conserver, 50 par défaut) pour
 * entamer la nouvelle semaine. Un SMS encore en attente n'est jamais supprimé
 * (son envoi échouerait). Les codes de validation et les transactions ne sont
 * pas concernés (voir PurgerDonneesTechniques).
 */
class PurgerHistoriqueSms
{
    /**
     * @return int nombre de SMS supprimés
     */
    public function __invoke(): int
    {
        $conserver = max(0, (int) config('plateforme.retention.messages_sms_conserver', 50));

        // Identifiant du plus ancien SMS à conserver ; aucun si l'historique est plus court.
        $seuil = MessageSms::query()->orderByDesc('id')->skip($conserver)->take(1)->value('id');

        if ($seuil === null) {
            return 0;
        }

        $supprimes = MessageSms::query()
            ->where('id', '<=', $seuil)
            ->whereIn('statut', [StatutLivraison::Envoyee, StatutLivraison::Echec])
            ->delete();

        if ($supprimes > 0) {
            JournaliserAudit::enregistrer('donnees.purgees', donnees: ['messages_sms' => $supprimes]);
        }

        return $supprimes;
    }
}
