<?php

namespace App\Actions\Comptes;

use App\Enums\Role;
use App\Exceptions\OperationCompteException;
use App\Models\User;
use App\Services\GenerateurPin;

/**
 * Le superadmin gère lui-même son mot de passe (5 chiffres, comme tous les
 * comptes) depuis Mon profil : aucun autre compte ne peut le faire pour lui.
 * Seule exception à la règle « jamais sur son propre compte », limitée au mot
 * de passe. Journalisation par UserObserver (sans le mot de passe).
 */
class MotDePasseSuperadminAction
{
    /**
     * @throws OperationCompteException
     */
    public function definir(User $superadmin, string $motDePasse): void
    {
        $this->enregistrer($superadmin, $motDePasse);
    }

    /**
     * @return string mot de passe généré, à afficher une seule fois
     *
     * @throws OperationCompteException
     */
    public function generer(User $superadmin): string
    {
        $motDePasse = GenerateurPin::generer();
        $this->enregistrer($superadmin, $motDePasse);

        return $motDePasse;
    }

    private function enregistrer(User $superadmin, string $motDePasse): void
    {
        if (! $superadmin->hasRole(Role::Superadmin)) {
            throw new OperationCompteException('Seul le superadmin modifie lui-même son mot de passe.');
        }

        $superadmin->forceFill(['password' => $motDePasse, 'tentatives_echouees' => 0, 'verrouille_le' => null])->save();
    }
}
