<?php

namespace App\Services\Droits;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/**
 * Règles anti-élévation de privilèges pour la gestion des rôles, des
 * permissions et des comptes. Le superadmin passe toutes les règles sauf
 * celles qui protègent l'intégrité du système (rôle superadmin verrouillé,
 * espaces jamais mélangés).
 */
class GardeDroits
{
    /**
     * Peut-on ajouter ou retirer cette permission sur ce rôle ?
     * - le rôle superadmin est verrouillé ;
     * - une permission d'un espace n'est jamais attribuable à un rôle de l'autre ;
     * - il faut « gérer les rôles » ET détenir soi-même la permission ;
     * - on ne modifie pas un rôle que l'on détient (pas d'auto-attribution).
     */
    public static function peutModifierPermissionDuRole(User $acteur, Role $role, Permission $permission): bool
    {
        if ($role->estVerrouille() || $permission->espace() !== $role->espace()) {
            return false;
        }

        if ($acteur->hasRole(Role::Superadmin)) {
            return true;
        }

        return $acteur->can(Permission::GererRoles->value)
            && $acteur->can($permission->value)
            && ! $acteur->hasRole($role);
    }

    /**
     * Peut-on attribuer ce rôle à ce compte ?
     * - jamais à soi-même ;
     * - superadmin et admin : uniquement par un superadmin ;
     * - agent : par quiconque « gère les utilisateurs » ;
     * - partenaire : uniquement via la gestion des opérateurs d'un partenaire.
     */
    public static function peutAttribuerRole(User $acteur, User $cible, Role $role): bool
    {
        if ($acteur->is($cible)) {
            return false;
        }

        return match ($role) {
            Role::Superadmin, Role::Admin => $acteur->hasRole(Role::Superadmin),
            Role::Agent => $acteur->hasRole(Role::Superadmin)
                || ($acteur->can(Permission::GererUtilisateurs->value) && ! $cible->hasAnyRole([Role::Superadmin, Role::Admin])),
            Role::Partenaire => $acteur->can(Permission::GererOperateursPartenaires->value),
        };
    }

    /**
     * Peut-on modifier (verrouiller, réinitialiser le PIN, désactiver) ce compte ?
     * Un compte ne peut être géré que par quelqu'un de rang supérieur.
     */
    public static function peutGererCompte(User $acteur, User $cible): bool
    {
        if ($acteur->is($cible) || $cible->hasRole(Role::Superadmin)) {
            return false;
        }

        if ($acteur->hasRole(Role::Superadmin)) {
            return true;
        }

        if ($cible->hasRole(Role::Admin)) {
            return false;
        }

        return $cible->hasRole(Role::Partenaire)
            ? $acteur->can(Permission::GererOperateursPartenaires->value)
            : $acteur->can(Permission::GererUtilisateurs->value);
    }
}
