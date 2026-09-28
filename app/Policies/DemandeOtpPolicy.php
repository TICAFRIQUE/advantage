<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DemandeOtp;
use App\Models\User;
use App\Services\PartenaireCourant;

/**
 * Un code n'est utilisable que par le partenaire qui l'a demandé.
 */
class DemandeOtpPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::VerifierCarte->value) && PartenaireCourant::pour($user) !== null;
    }

    public function valider(User $user, DemandeOtp $demande): bool
    {
        return $user->can(Permission::ConfirmerOtp->value)
            && $demande->partenaire_id === PartenaireCourant::pour($user)?->id;
    }
}
