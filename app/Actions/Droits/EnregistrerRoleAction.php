<?php

namespace App\Actions\Droits;

use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\OperationRoleException;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Création, modification (libellé, permissions) et suppression des rôles.
 *
 * Chaque permission ajoutée ou retirée est revérifiée par GardeDroits : une
 * case que l'auteur ne peut pas modifier conserve sa valeur actuelle, et
 * toute tentative de la changer est refusée. Les rôles personnalisés
 * appartiennent au back-office (espace « gestion »).
 */
class EnregistrerRoleAction
{
    /**
     * @param  list<string>  $permissions
     *
     * @throws OperationRoleException
     */
    public function creer(string $libelle, array $permissions, User $auteur): RoleUtilisateur
    {
        if (! $auteur->hasRole(Role::Superadmin) && ! $auteur->can(Permission::GererRoles->value)) {
            throw new OperationRoleException('Vous n\'avez pas le droit de créer un rôle.');
        }

        return DB::transaction(function () use ($libelle, $permissions, $auteur): RoleUtilisateur {
            $role = new RoleUtilisateur;
            $role->forceFill([
                'name' => $this->nomUnique($libelle),
                'guard_name' => 'web',
                'libelle' => $libelle,
                'espace' => 'gestion',
                'systeme' => false,
                'cree_par_id' => $auteur->id,
            ])->save();

            $this->appliquerPermissions($role, $permissions, $auteur);

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws OperationRoleException
     */
    public function modifier(RoleUtilisateur $role, ?string $libelle, array $permissions, User $auteur): RoleUtilisateur
    {
        if ($role->estVerrouille()) {
            throw new OperationRoleException('Ce rôle a tous les droits : il n\'est pas modifiable.');
        }

        return DB::transaction(function () use ($role, $libelle, $permissions, $auteur): RoleUtilisateur {
            $role = RoleUtilisateur::query()->lockForUpdate()->findOrFail($role->id);

            if ($libelle !== null && ! $role->estSysteme() && $libelle !== $role->getAttributes()['libelle']) {
                if (! GardeDroits::peutGererRole($auteur, $role)) {
                    throw new OperationRoleException('Vous n\'avez pas le droit de renommer ce rôle.');
                }

                $role->forceFill(['libelle' => $libelle]);
            }

            $modifie = $this->appliquerPermissions($role, $permissions, $auteur);

            if ($modifie || $role->isDirty()) {
                $role->forceFill(['modifie_par_id' => $auteur->id])->save();
            }

            return $role;
        });
    }

    /**
     * Un rôle encore porté par un compte (supprimé compris) n'est pas supprimable.
     *
     * @throws OperationRoleException
     */
    public function supprimer(RoleUtilisateur $role, User $auteur): void
    {
        if (! GardeDroits::peutGererRole($auteur, $role)) {
            throw new OperationRoleException($role->estSysteme()
                ? 'Un rôle système ne peut pas être supprimé.'
                : 'Vous n\'avez pas le droit de supprimer ce rôle.');
        }

        DB::transaction(function () use ($role): void {
            $role = RoleUtilisateur::query()->lockForUpdate()->findOrFail($role->id);

            if (User::withTrashed()->role($role->name)->exists()) {
                throw new OperationRoleException('Ce rôle est encore attribué à des comptes : changez d\'abord leur rôle.');
            }

            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permissions finales = permissions actuelles que l'auteur ne peut pas
     * modifier + permissions demandées parmi celles qu'il peut modifier.
     *
     * @param  list<string>  $demandees
     * @return bool vrai si une permission a changé
     *
     * @throws OperationRoleException
     */
    private function appliquerPermissions(RoleUtilisateur $role, array $demandees, User $auteur): bool
    {
        $actuelles = $role->permissions()->pluck('name')->all();
        $modifiables = array_values(array_filter(
            array_map(fn (Permission $p) => $p->value, Permission::cases()),
            fn (string $p) => GardeDroits::peutModifierPermissionDuRole($auteur, $role, Permission::from($p)),
        ));

        $interdites = array_diff(array_diff($demandees, $actuelles), $modifiables);

        if ($interdites !== []) {
            throw new OperationRoleException('Vous ne pouvez pas attribuer ces permissions : '.implode(', ', array_map(
                fn (string $p) => Permission::tryFrom($p)?->libelle() ?? $p,
                $interdites,
            )).'.');
        }

        $finales = array_values(array_unique([
            ...array_diff($actuelles, $modifiables),
            ...array_intersect($demandees, $modifiables),
        ]));

        $ajoutees = array_values(array_diff($finales, $actuelles));
        $retirees = array_values(array_diff($actuelles, $finales));

        if ($ajoutees !== []) {
            $role->givePermissionTo($ajoutees);
        }

        if ($retirees !== []) {
            $role->revokePermissionTo($retirees);
        }

        return $ajoutees !== [] || $retirees !== [];
    }

    private function nomUnique(string $libelle): string
    {
        $base = Str::slug($libelle) ?: 'role';
        $nom = $base;

        for ($i = 2; RoleUtilisateur::query()->where('name', $nom)->exists() || Role::tryFrom($nom) !== null; $i++) {
            $nom = "{$base}-{$i}";
        }

        return $nom;
    }
}
