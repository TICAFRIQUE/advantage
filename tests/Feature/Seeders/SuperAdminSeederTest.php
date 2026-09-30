<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'plateforme.superadmin.nom_utilisateur' => 'SuperAdmin',
        'plateforme.superadmin.pin' => '48157',
    ]);
});

it('creates the superadmin account with the configured pin', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);

    $superadmin = User::where('nom_utilisateur', 'superadmin')->sole();

    expect($superadmin->hasRole(Role::Superadmin))->toBeTrue()
        ->and(Hash::check('48157', $superadmin->password))->toBeTrue();
});

it('generates a pin shown once when none is configured', function () {
    config(['plateforme.superadmin.pin' => null]);

    $this->artisan('db:seed', ['--class' => RolesEtPermissionsSeeder::class])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => SuperAdminSeeder::class])
        ->expectsOutputToContain('PIN généré')
        ->assertSuccessful();

    expect(User::where('nom_utilisateur', 'superadmin')->exists())->toBeTrue();
});

it('never overwrites the pin of an existing superadmin', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
    config(['plateforme.superadmin.pin' => '73920']);

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('nom_utilisateur', 'superadmin')->count())->toBe(1)
        ->and(Hash::check('48157', User::where('nom_utilisateur', 'superadmin')->value('password')))->toBeTrue();
});

it('refuses a superadmin pin that is not exactly five digits', function (string $pin) {
    config(['plateforme.superadmin.pin' => $pin]);

    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
})->throws(RuntimeException::class, 'exactement 5 chiffres')->with([
    'trop court' => ['4815'],
    'trop long' => ['481573'],
    'lettres' => ['48a57'],
    'ancien mot de passe' => ['Motdepasse-Fort-2026'],
]);

it('refuses a trivial superadmin pin', function (string $pin) {
    config(['plateforme.superadmin.pin' => $pin]);

    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
})->throws(RuntimeException::class, 'trop simple')->with(['11111', '12345', '54321']);

it('restores a deleted superadmin account', function () {
    $this->seed([RolesEtPermissionsSeeder::class, SuperAdminSeeder::class]);
    User::where('nom_utilisateur', 'superadmin')->sole()->delete();

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('nom_utilisateur', 'superadmin')->exists())->toBeTrue();
});
