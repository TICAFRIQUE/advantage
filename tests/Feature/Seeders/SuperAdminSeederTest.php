<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'plateforme.superadmin.nom_utilisateur' => 'SuperAdmin',
        'plateforme.superadmin.mot_de_passe' => 'Motdepasse-Fort-2026',
    ]);
});

it('creates the superadmin account from configuration', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);

    $superadmin = User::where('nom_utilisateur', 'superadmin')->sole();

    expect($superadmin->hasRole(Role::Superadmin))->toBeTrue()
        ->and(Hash::check('Motdepasse-Fort-2026', $superadmin->password))->toBeTrue();
});

it('never overwrites the password of an existing superadmin', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
    config(['plateforme.superadmin.mot_de_passe' => 'Autre-Motdepasse-2026']);

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('nom_utilisateur', 'superadmin')->count())->toBe(1)
        ->and(Hash::check('Motdepasse-Fort-2026', User::where('nom_utilisateur', 'superadmin')->value('password')))->toBeTrue();
});

it('refuses a superadmin password shorter than twelve characters', function () {
    config(['plateforme.superadmin.mot_de_passe' => '12345']);

    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
})->throws(RuntimeException::class, 'au moins 12 caractères');

it('restores a deleted superadmin account', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
    User::where('nom_utilisateur', 'superadmin')->sole()->delete();

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('nom_utilisateur', 'superadmin')->exists())->toBeTrue();
});
