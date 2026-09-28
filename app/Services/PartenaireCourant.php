<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutPartenaire;
use App\Models\Partenaire;
use App\Models\User;

/**
 * Partenaire pour le compte duquel l'utilisateur agit dans l'espace partenaire :
 * - opérateur partenaire : toujours son propre partenaire (non modifiable) ;
 * - admin / superadmin : le partenaire choisi, mémorisé en session.
 * Un partenaire inactif n'est jamais retenu.
 */
class PartenaireCourant
{
    public const CLE_SESSION = 'partenaire_courant_id';

    public static function pour(User $user): ?Partenaire
    {
        $partenaire = self::peutChoisir($user)
            ? Partenaire::query()->find(session(self::CLE_SESSION))
            : $user->partenaire;

        return $partenaire?->statut === StatutPartenaire::Actif ? $partenaire : null;
    }

    /**
     * Seuls l'admin et le superadmin choisissent le partenaire (option A).
     */
    public static function peutChoisir(User $user): bool
    {
        return $user->hasAnyRole([Role::Superadmin, Role::Admin]);
    }

    public static function definir(Partenaire $partenaire): void
    {
        session([self::CLE_SESSION => $partenaire->id]);
    }

    public static function oublier(): void
    {
        session()->forget(self::CLE_SESSION);
    }
}
