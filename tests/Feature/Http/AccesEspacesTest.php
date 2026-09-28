<?php

use App\Enums\Role;
use App\Models\Partenaire;
use App\Models\User;

it('redirects guests to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['gestion.tableau-de-bord', 'partenaire.tableau-de-bord', 'accueil-espace']);

it('sends each role to its space after login', function (Role $role, string $route) {
    connecter(utilisateurAvecRole($role))->get(route('accueil-espace'))->assertRedirect(route($route));
})->with([
    'superadmin' => [Role::Superadmin, 'gestion.tableau-de-bord'],
    'admin' => [Role::Admin, 'gestion.tableau-de-bord'],
    'agent' => [Role::Agent, 'gestion.tableau-de-bord'],
    'partenaire' => [Role::Partenaire, 'partenaire.tableau-de-bord'],
]);

it('shares the same back-office between superadmin, admin and agent', function (Role $role) {
    connecter(utilisateurAvecRole($role))->get(route('gestion.tableau-de-bord'))->assertOk();
})->with([Role::Superadmin, Role::Admin, Role::Agent]);

it('keeps partner operators out of the back-office', function () {
    connecter(utilisateurAvecRole(Role::Partenaire))->get(route('gestion.tableau-de-bord'))->assertForbidden();
});

it('keeps the partner space for partner operators only', function (Role $role) {
    connecter(utilisateurAvecRole($role))->get(route('partenaire.tableau-de-bord'))->assertForbidden();
})->with([Role::Superadmin, Role::Admin, Role::Agent]);

it('lets an agent do only what its permissions allow', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    connecter($agent)->get(route('gestion.cartes.index'))->assertOk();
    connecter($agent)->get(route('gestion.transaction.verifier'))->assertForbidden();

    $agent->givePermissionTo('effectuer-transaction-partenaire');

    connecter($agent->fresh())->get(route('gestion.transaction.verifier'))->assertOk();
});

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
