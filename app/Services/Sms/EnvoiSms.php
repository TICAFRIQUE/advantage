<?php

namespace App\Services\Sms;

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Jobs\EnvoyerSmsJob;
use App\Models\MessageSms;

/**
 * Point d'entrée unique pour émettre un SMS : enregistre le message (contenu
 * chiffré) puis confie l'envoi à la file d'attente — jamais d'appel au
 * fournisseur pendant la requête HTTP.
 */
class EnvoiSms
{
    public function __construct(private PasserelleSms $passerelle) {}

    /**
     * @param  string  $telephone  numéro au format E.164
     */
    public function envoyer(string $telephone, string $contenu, TypeSms $type): MessageSms
    {
        $message = MessageSms::create([
            'telephone' => $telephone,
            'type' => $type,
            'contenu' => $contenu,
            'statut' => StatutLivraison::EnAttente,
            'fournisseur' => $this->passerelle->nom(),
        ]);

        // Après validation de la transaction englobante : le job doit trouver le message.
        EnvoyerSmsJob::dispatch($message)->afterCommit();

        return $message;
    }
}
