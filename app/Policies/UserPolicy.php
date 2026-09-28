<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Droits\GardeDroits;

/**
 * Gestion d'un compte (PIN, verrouillage, statut) : règles anti-élévation
 * de GardeDroits (rang supérieur requis, jamais soi-même ni un superadmin).
 */
class UserPolicy
{
    public function gerer(User $acteur, User $compte): bool
    {
        return GardeDroits::peutGererCompte($acteur, $compte);
    }
}
