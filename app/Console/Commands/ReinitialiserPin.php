<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\GenerateurPin;
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
    public function handle(): int
    {
        $user = User::query()->where('nom_utilisateur', mb_strtolower(trim($this->argument('nom_utilisateur'))))->first();

        if ($user === null) {
            $this->components->error('Aucun compte actif ne porte ce nom d\'utilisateur.');

            return self::FAILURE;
        }

        if ($user->hasRole(Role::Superadmin)) {
            $this->components->error('Le superadmin utilise un mot de passe fort, pas un PIN.');

            return self::FAILURE;
        }

        $pin = GenerateurPin::generer();

        $user->forceFill([
            'password' => $pin,
            'tentatives_echouees' => 0,
            'verrouille_le' => null,
        ])->save();

        $this->components->info("Nouveau PIN pour « {$user->nom_utilisateur} » : {$pin}");
        $this->components->warn('Transmettez-le à l\'utilisateur ; il ne sera plus affiché.');

        return self::SUCCESS;
    }
}
