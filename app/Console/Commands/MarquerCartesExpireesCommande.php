<?php

namespace App\Console\Commands;

use App\Actions\Cartes\MarquerCartesExpirees;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cartes:marquer-expirees')]
#[Description('Passe au statut « expirée » les cartes dont l\'échéance est dépassée')]
class MarquerCartesExpireesCommande extends Command
{
    public function handle(MarquerCartesExpirees $marquer): int
    {
        $nombre = $marquer();

        $this->components->info("{$nombre} carte(s) passée(s) au statut « expirée ».");

        return self::SUCCESS;
    }
}
