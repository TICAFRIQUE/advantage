<?php

namespace App\Console\Commands;

use App\Actions\Maintenance\PurgerDonneesTechniques;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('donnees:purger')]
#[Description('Supprime les codes de validation et SMS anciens (rétention configurée)')]
class PurgerDonneesTechniquesCommande extends Command
{
    public function handle(PurgerDonneesTechniques $purger): int
    {
        $resultat = $purger();

        $this->components->twoColumnDetail('Demandes de code supprimées', (string) $resultat['demandes_otp']);
        $this->components->twoColumnDetail('SMS supprimés', (string) $resultat['messages_sms']);

        return self::SUCCESS;
    }
}
