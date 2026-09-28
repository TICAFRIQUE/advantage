<?php

use App\Enums\Role;
use App\Models\Partenaire;
use App\Models\User;

it('redirects guests to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['admin.tableau-de-bord', 'agent.tableau-de-bord', 'partenaire.tableau-de-bord', 'accueil-espace']);

it('grants each role its own space', function (Role $role, string $route) {
    connecter(utilisateurAvecRole($role))->get(route($route))->assertOk();
})->with([
    'admin' => [Role::Admin, 'admin.tableau-de-bord'],
    'agent' => [Role::Agent, 'agent.tableau-de-bord'],
    'partenaire' => [Role::Partenaire, 'partenaire.tableau-de-bord'],
]);

it('forbids a role from entering another space', function (Role $role, string $route) {
    connecter(utilisateurAvecRole($role))->get(route($route))->assertForbidden();
})->with([
    'agent → admin' => [Role::Agent, 'admin.tableau-de-bord'],
    'partenaire → admin' => [Role::Partenaire, 'admin.tableau-de-bord'],
    'partenaire → agent' => [Role::Partenaire, 'agent.tableau-de-bord'],
    'agent → partenaire' => [Role::Agent, 'partenaire.tableau-de-bord'],
]);

it('lets the superadmin and the admin enter every space', function (Role $role, string $route) {
    connecter(utilisateurAvecRole($role))->get(route($route))->assertOk();
})->with([Role::Superadmin, Role::Admin])->with(['admin.tableau-de-bord', 'agent.tableau-de-bord', 'partenaire.tableau-de-bord']);

it('grants the superadmin any permission, including ones not assigned to a role', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    expect($superadmin->can('gerer-roles'))->toBeTrue()
        ->and($superadmin->can('permission-future-inexistante'))->toBeTrue()
        ->and(utilisateurAvecRole(Role::Admin)->can('gerer-roles'))->toBeFalse();
});

it('forbids a partner operator whose partner is inactive', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire, [
        'partenaire_id' => Partenaire::factory()->inactif()->create()->id,
    ]);

    connecter($operateur)->get(route('partenaire.tableau-de-bord'))->assertForbidden();
});

it('forbids a partner operator attached to no partner', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire, ['partenaire_id' => null]);

    connecter($operateur)->get(route('partenaire.tableau-de-bord'))->assertForbidden();
});

it('logs out an account without any known role', function () {
    $sansRole = User::factory()->create();

    connecter($sansRole)->get(route('accueil-espace'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('nom_utilisateur');

    $this->assertGuest();
});

it('redirects an authenticated user away from the login page', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('login'))->assertRedirect(route('accueil-espace'));
});

it('renders the login page with csrf protection and numeric keypad', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('inputmode="numeric"', false);
});
