<?php

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesEtPermissionsSeeder;
use Spatie\Permission\Models\Role as ModeleRole;

it('gives each role exactly its permissions', function (Role $role) {
    $this->seed(RolesEtPermissionsSeeder::class);

    $attendues = array_map(fn (Permission $p) => $p->value, $role->permissions());

    expect(ModeleRole::findByName($role->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect($attendues)->sort()->values()->all());
})->with(Role::cases());

it('keeps role and settings management for the superadmin only', function () {
    expect(Role::Admin->permissions())
        ->not->toContain(Permission::GererRoles)
        ->not->toContain(Permission::GererParametres);
});

it('can run twice without duplicating anything', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    $this->seed(RolesEtPermissionsSeeder::class);

    expect(ModeleRole::count())->toBe(count(Role::cases()))
        ->and(Spatie\Permission\Models\Permission::count())->toBe(count(Permission::cases()));
});
