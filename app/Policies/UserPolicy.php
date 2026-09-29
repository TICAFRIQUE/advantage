<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Droits\GardeDroits;

/**
 * Gestion d'un compte (fiche, PIN, verrouillage, statut, suppression) : règles anti-élévation
 * de GardeDroits (rang supérieur requis, jamais soi-même ni un superadmin).
 */
class UserPolicy
{
    /**
     * Capacités exclues du passe-droit du superadmin (Gate::before).
     *
     * @var list<string>
     */
    public const CAPACITES = ['gerer', 'reinitialiserPin', 'supprimer'];

    public function gerer(User $acteur, User $compte): bool
    {
        return GardeDroits::peutGererCompte($acteur, $compte);
    }

    public function reinitialiserPin(User $acteur, User $compte): bool
    {
        return $acteur->can(Permission::ReinitialiserPin->value) && GardeDroits::peutGererCompte($acteur, $compte);
    }

    public function supprimer(User $acteur, User $compte): bool
    {
        return $acteur->can(Permission::SupprimerComptes->value) && GardeDroits::peutGererCompte($acteur, $compte);
    }
}
