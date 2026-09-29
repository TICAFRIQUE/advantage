<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes\GestionSauvegardes;
use App\Services\Sauvegardes\SauvegardeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sauvegarde:creer')]
#[Description('Sauvegarde la base de données (dossier paramétré, 10 dernières conservées)')]
class CreerSauvegardeCommande extends Command
{
    public function handle(GestionSauvegardes $sauvegardes): int
    {
        try {
            $nom = $sauvegardes->creer(null);
        } catch (SauvegardeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Sauvegarde créée : {$sauvegardes->dossier()}/{$nom}");

        return self::SUCCESS;
    }
}
