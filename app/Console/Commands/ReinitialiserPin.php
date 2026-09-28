<?php

namespace App\Console\Commands;

use App\Actions\Comptes\GererCompteAction;
use App\Exceptions\OperationCompteException;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Outil de secours (accès serveur) : génère un nouveau PIN, déverrouille le
 * compte et remet le compteur d'échecs à zéro. Le PIN n'est affiché qu'une
 * fois ; l'opération est journalisée (sans le PIN) par UserObserver.
 */
#[Signature('utilisateur:reinitialiser-pin {nom_utilisateur : Nom d\'utilisateur du compte}')]
#[Description('Génère un nouveau PIN pour un compte et le déverrouille')]
class ReinitialiserPin extends Command
{
    public function handle(GererCompteAction $gerer): int
    {
        $user = User::query()->where('nom_utilisateur', mb_strtolower(trim($this->argument('nom_utilisateur'))))->first();

        if ($user === null) {
            $this->components->error('Aucun compte actif ne porte ce nom d\'utilisateur.');

            return self::FAILURE;
        }

        try {
            // Auteur null : exécution console (accès serveur).
            $pin = $gerer->reinitialiserPin($user, null);
        } catch (OperationCompteException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Nouveau PIN pour « {$user->nom_utilisateur} » : {$pin}");
        $this->components->warn('Transmettez-le à l\'utilisateur ; il ne sera plus affiché.');

        return self::SUCCESS;
    }
}
