<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DemandeOtp;
use App\Models\User;
use App\Services\PartenaireCourant;

/**
 * Un code n'est utilisable que par le partenaire qui l'a demandé.
 * Opérateur : « effectuer une transaction » ; back-office : « effectuer une
 * transaction pour un partenaire » (les deux espaces ne se mélangent jamais).
 */
class DemandeOtpPolicy
{
    public function create(User $user): bool
    {
        return self::peutEffectuerTransaction($user) && PartenaireCourant::pour($user) !== null;
    }

    public function valider(User $user, DemandeOtp $demande): bool
    {
        return self::peutEffectuerTransaction($user)
            && $demande->partenaire_id === PartenaireCourant::pour($user)?->id;
    }

    public static function peutEffectuerTransaction(User $user): bool
    {
        return $user->canAny([Permission::EffectuerTransaction->value, Permission::EffectuerTransactionPartenaire->value]);
    }
}
