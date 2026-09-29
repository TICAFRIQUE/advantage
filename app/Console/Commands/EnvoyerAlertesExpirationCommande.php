<?php

namespace App\Console\Commands;

use App\Actions\Cartes\EnvoyerAlertesExpiration;
use App\Enums\PalierAlerte;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cartes:alertes-expiration')]
#[Description('Prévient par SMS les titulaires dont la carte expire dans 3, 2 ou 1 mois')]
class EnvoyerAlertesExpirationCommande extends Command
{
    public function handle(EnvoyerAlertesExpiration $envoyer): int
    {
        if (! config('plateforme.alertes_expiration.sms')) {
            $this->components->warn('Alertes SMS désactivées (ALERTES_EXPIRATION_SMS=false).');

            return self::SUCCESS;
        }

        foreach ($envoyer() as $palier => $nombre) {
            $this->components->twoColumnDetail('Échéance dans '.PalierAlerte::from($palier)->libelle(), "{$nombre} SMS");
        }

        return self::SUCCESS;
    }
}
