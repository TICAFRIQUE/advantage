<?php

namespace App\Console\Commands;

use App\Actions\Maintenance\PurgerHistoriqueSms;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:purger-historique')]
#[Description("Ne conserve que les SMS les plus récents de l'historique (50 par défaut)")]
class PurgerHistoriqueSmsCommande extends Command
{
    public function handle(PurgerHistoriqueSms $purger): int
    {
        $this->components->twoColumnDetail('SMS supprimés', (string) $purger());
        $this->components->twoColumnDetail('SMS conservés (les plus récents)', (string) config('plateforme.retention.messages_sms_conserver', 50));

        return self::SUCCESS;
    }
}
