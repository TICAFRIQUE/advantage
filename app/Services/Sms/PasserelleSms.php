<?php

namespace App\Services\Sms;

/**
 * Contrat d'un fournisseur SMS. Un pilote par fournisseur ; le pilote actif
 * est choisi par la configuration (plateforme.sms.driver).
 */
interface PasserelleSms
{
    /**
     * Identifiant court du pilote (enregistré avec chaque message).
     */
    public function nom(): string;

    /**
     * Envoie un SMS. Ne doit jamais journaliser le contenu (codes OTP).
     *
     * @param  string  $telephone  numéro au format E.164 (+225…)
     */
    public function envoyer(string $telephone, string $message): ResultatEnvoiSms;
}
