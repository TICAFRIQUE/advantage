<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Services\Droits\SynchroniserPermissions;
use Database\Seeders\RolesEtPermissionsSeeder;
use Spatie\Permission\Models\Permission as ModelePermission;
use Spatie\Permission\Models\Role as ModeleRole;

function permissionsDuRole(string $role): array
{
    return ModeleRole::findByName($role)->permissions->pluck('name')->sort()->values()->all();
}

it('declares in the enum exactly the permissions of the configuration', function () {
    expect(collect(Permission::valeurs())->sort()->values()->all())
        ->toBe(collect(array_keys(Permission::definitions()))->sort()->values()->all());
});

it('gives each role its default permissions on first run', function (Role $role) {
    $this->seed(RolesEtPermissionsSeeder::class);

    expect(permissionsDuRole($role->value))->toBe(collect($role->permissionsParDefaut())->sort()->values()->all());
})->with(Role::cases());

it('keeps roles and settings management for the superadmin by default', function () {
    expect(Role::Admin->permissionsParDefaut())
        ->not->toContain('gerer-roles')
        ->not->toContain('purger-journal-audit');
});

it('never mixes back-office and partner permissions in a default role', function (Role $role) {
    $horsEspace = array_filter($role->permissionsParDefaut(), fn (string $p) => Permission::from($p)->espace() !== $role->espace());

    expect($horsEspace)->toBe([]);
})->with(Role::cases());

it('can run twice without creating any duplicate', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    $rapport = app(SynchroniserPermissions::class)();

    expect($rapport['permissions_creees'])->toBe([])
        ->and($rapport['roles_crees'])->toBe([])
        ->and(ModeleRole::count())->toBe(count(Role::cases()))
        ->and(ModelePermission::count())->toBe(count(Permission::cases()));
});

it('never re-grants a permission that was removed in the settings', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    ModeleRole::findByName('agent')->revokePermissionTo('voir-rapport-cartes');

    app(SynchroniserPermissions::class)();

    expect(permissionsDuRole('agent'))->not->toContain('voir-rapport-cartes');
});

it('never removes a permission granted in the settings', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    ModeleRole::findByName('agent')->givePermissionTo('effectuer-transaction-partenaire');

    app(SynchroniserPermissions::class)();

    expect(permissionsDuRole('agent'))->toContain('effectuer-transaction-partenaire');
});

it('grants a newly declared permission to its default roles only once', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    ModelePermission::findByName('exporter-donnees')->delete();

    $rapport = app(SynchroniserPermissions::class)();

    expect($rapport['permissions_creees'])->toBe(['exporter-donnees'])
        ->and(permissionsDuRole('admin'))->toContain('exporter-donnees')
        ->and(permissionsDuRole('agent'))->not->toContain('exporter-donnees');
});

it('keeps obsolete permissions unless explicitly asked to delete them', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    ModelePermission::create(['name' => 'ancienne-permission', 'guard_name' => 'web']);

    expect(app(SynchroniserPermissions::class)()['obsoletes'])->toBe(['ancienne-permission'])
        ->and(ModelePermission::where('name', 'ancienne-permission')->exists())->toBeTrue();

    app(SynchroniserPermissions::class)(supprimerObsoletes: true);

    expect(ModelePermission::where('name', 'ancienne-permission')->exists())->toBeFalse();
});

it('removes a permission held by a role of the other space', function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    ModeleRole::findByName('admin')->givePermissionTo('acceder-espace-partenaire');

    $rapport = app(SynchroniserPermissions::class)();

    expect($rapport['incoherences_retirees'])->toBe(['admin : acceder-espace-partenaire'])
        ->and(permissionsDuRole('admin'))->not->toContain('acceder-espace-partenaire');
});

it('is available as an artisan command', function () {
    $this->artisan('permissions:synchroniser')->assertSuccessful();

    expect(ModelePermission::count())->toBe(count(Permission::cases()));
});
