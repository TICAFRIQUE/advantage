<?php

namespace App\Services\Droits;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\RoleUtilisateur;
use App\Models\User;

/**
 * Règles anti-élévation de privilèges pour la gestion des rôles, des
 * permissions et des comptes. Le superadmin passe toutes les règles sauf
 * celles qui protègent l'intégrité du système (rôle superadmin verrouillé,
 * espaces jamais mélangés).
 *
 * Les rôles sont acceptés sous forme d'enum (rôles système), de nom ou de
 * modèle : les rôles personnalisés suivent les mêmes règles que « agent ».
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
    public static function peutModifierPermissionDuRole(User $acteur, Role|RoleUtilisateur|string $role, Permission $permission): bool
    {
        $role = RoleUtilisateur::depuis($role);

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
     * - agent : par quiconque « gère les utilisateurs » et détient toutes les
     *   permissions du rôle (sinon on s'octroierait des droits par ce compte) ;
     * - partenaire : uniquement via la gestion des opérateurs d'un partenaire ;
     * - rôle personnalisé du back-office : mêmes règles que « agent ».
     */
    public static function peutAttribuerRole(User $acteur, User $cible, Role|RoleUtilisateur|string $role): bool
    {
        if ($acteur->is($cible)) {
            return false;
        }

        $role = RoleUtilisateur::depuis($role);

        return match ($role->systeme()) {
            Role::Superadmin, Role::Admin => $acteur->hasRole(Role::Superadmin),
            Role::Partenaire => $acteur->can(Permission::GererOperateursPartenaires->value),
            // Agent et rôles personnalisés du back-office.
            default => $role->espace() === 'gestion' && ($acteur->hasRole(Role::Superadmin)
                || ($acteur->can(Permission::GererUtilisateurs->value)
                    && ! $cible->hasAnyRole([Role::Superadmin, Role::Admin])
                    && self::detientToutes($acteur, $role->permissions->pluck('name')))),
        };
    }

    /**
     * Peut-on renommer ou supprimer ce rôle personnalisé ? Jamais un rôle
     * système ni son propre rôle ; il faut « gérer les rôles » et détenir
     * toutes ses permissions (on ne touche pas à un rôle plus puissant que soi).
     */
    public static function peutGererRole(User $acteur, Role|RoleUtilisateur|string $role): bool
    {
        $role = RoleUtilisateur::depuis($role);

        if ($role->estSysteme()) {
            return false;
        }

        return $acteur->hasRole(Role::Superadmin)
            || ($acteur->can(Permission::GererRoles->value)
                && ! $acteur->hasRole($role)
                && self::detientToutes($acteur, $role->permissions->pluck('name')));
    }

    /**
     * Peut-on modifier (verrouiller, réinitialiser le PIN, désactiver) ce compte ?
     * Un compte ne peut être géré que par quelqu'un de rang supérieur. Pour un
     * compte du back-office, il faut en plus détenir toutes ses permissions :
     * réinitialiser son PIN permettrait sinon de se connecter à sa place et
     * d'obtenir des droits que l'on n'a pas.
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
            : $acteur->can(Permission::GererUtilisateurs->value)
                && self::detientToutes($acteur, $cible->getAllPermissions()->pluck('name'));
    }

    /**
     * Rôles du back-office que l'acteur peut donner à ce compte (ou à un
     * nouveau compte). Le superadmin n'est jamais attribué depuis l'interface.
     *
     * @return list<RoleUtilisateur>
     */
    public static function rolesGestionAttribuables(User $acteur, ?User $cible = null): array
    {
        return RoleUtilisateur::query()->deLEspace('gestion')->where('name', '!=', Role::Superadmin->value)
            ->with('permissions')->get()
            ->sortBy(fn (RoleUtilisateur $role) => [$role->rang(), $role->libelle()])
            ->filter(fn (RoleUtilisateur $role) => self::peutAttribuerRole($acteur, $cible ?? new User, $role))
            ->values()->all();
    }

    /**
     * @param  iterable<string>  $permissions
     */
    private static function detientToutes(User $acteur, iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $acteur->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
