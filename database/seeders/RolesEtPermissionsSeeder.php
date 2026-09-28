<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as ModelePermission;
use Spatie\Permission\Models\Role as ModeleRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée ou synchronise les rôles système et leurs permissions.
 * Idempotent : peut être relancé en production à chaque déploiement.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            ModelePermission::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            ModeleRole::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p) => $p->value, $role->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
