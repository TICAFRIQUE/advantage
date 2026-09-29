<?php

namespace App\Console\Commands;

use App\Services\JournaliserAudit;
use App\Services\Sms\PasserelleSms;
use App\Services\Telephone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vérifie la configuration du fournisseur SMS (accès serveur) : envoie
 * immédiatement, sans file d'attente, un SMS de test sans donnée sensible.
 */
#[Signature('sms:tester {telephone : Numéro destinataire (ex. 0707123456 ou +2250707123456)}')]
#[Description('Envoie un SMS de test avec le pilote configuré (SMS_DRIVER)')]
class TesterSms extends Command
{
    public function handle(): int
    {
        $telephone = Telephone::normaliser((string) $this->argument('telephone'));

        if ($telephone === null) {
            $this->components->error('Numéro de téléphone invalide.');

            return self::FAILURE;
        }

        try {
            $passerelle = app(PasserelleSms::class);
        } catch (Throwable $exception) {
            $this->components->error('Configuration SMS invalide : '.$exception->getMessage());

            return self::FAILURE;
        }

        $resultat = $passerelle->envoyer($telephone, config('app.name').' : SMS de test, configuration du fournisseur réussie.');

        JournaliserAudit::enregistrer('sms.test', donnees: ['pilote' => $passerelle->nom(), 'succes' => $resultat->succes]);

        if (! $resultat->succes) {
            $this->components->error("Échec avec le pilote « {$passerelle->nom()} » : {$resultat->erreur}");

            return self::FAILURE;
        }

        $this->components->info("SMS envoyé avec le pilote « {$passerelle->nom()} » (référence {$resultat->reference}) à ".Telephone::formater($telephone).'.');

        return self::SUCCESS;
    }
}
