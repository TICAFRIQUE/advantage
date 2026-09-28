<?php

namespace App\Actions\Comptes;

use App\Enums\Role;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\GenerateurPin;

/**
 * Opérations sur un compte existant. Chaque méthode revérifie les droits
 * (GardeDroits) : un compte n'est géré que par un rang supérieur, jamais
 * soi-même, jamais un superadmin. Auteur null = console (accès serveur).
 * La journalisation est assurée par UserObserver (sans le PIN).
 */
class GererCompteAction
{
    /**
     * Nouveau PIN (affiché une fois), compte déverrouillé, échecs remis à zéro.
     *
     * @throws OperationCompteException
     */
    public function reinitialiserPin(User $compte, ?User $auteur): string
    {
        $this->autoriser($compte, $auteur);

        if ($compte->hasRole(Role::Superadmin)) {
            throw new OperationCompteException('Le superadmin utilise un mot de passe fort, pas un PIN.');
        }

        $pin = GenerateurPin::generer();

        $compte->forceFill(['password' => $pin, 'tentatives_echouees' => 0, 'verrouille_le' => null])->save();

        return $pin;
    }

    /**
     * @throws OperationCompteException
     */
    public function verrouiller(User $compte, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['verrouille_le' => now()])->save();
    }

    /**
     * @throws OperationCompteException
     */
    public function deverrouiller(User $compte, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['verrouille_le' => null, 'tentatives_echouees' => 0])->save();
    }

    /**
     * Un compte désactivé est déconnecté à sa requête suivante (CompteActif).
     *
     * @throws OperationCompteException
     */
    public function changerStatut(User $compte, StatutUtilisateur $statut, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['statut' => $statut])->save();
    }

    private function autoriser(User $compte, ?User $auteur): void
    {
        if ($auteur !== null && ! GardeDroits::peutGererCompte($auteur, $compte)) {
            throw new OperationCompteException('Vous n\'avez pas le droit de gérer ce compte.');
        }
    }
}
