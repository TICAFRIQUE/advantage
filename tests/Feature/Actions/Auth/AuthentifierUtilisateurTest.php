<?php

use App\Enums\Role;
use App\Models\JournalAudit;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;

function tenterConnexion(string $nomUtilisateur, string $secret): TestResponse
{
    return formulaireConnexionAffiche()->from(route('login'))->post(route('login.store'), [
        'nom_utilisateur' => $nomUtilisateur,
        'password' => $secret,
    ]);
}

it('redirects each role to its own space after login', function (Role $role, string $route) {
    $user = utilisateurAvecRole($role);

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN)
        ->assertRedirect(route('accueil-espace'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('accueil-espace'))->assertRedirect(route($route));
})->with([
    'superadmin' => [Role::Superadmin, 'gestion.tableau-de-bord'],
    'admin' => [Role::Admin, 'gestion.tableau-de-bord'],
    'agent' => [Role::Agent, 'gestion.tableau-de-bord'],
    'partenaire' => [Role::Partenaire, 'partenaire.tableau-de-bord'],
]);

it('accepts the username regardless of case', function () {
    $user = utilisateurAvecRole(Role::Agent, ['nom_utilisateur' => 'Agent.Kouame']);

    expect($user->nom_utilisateur)->toBe('agent.kouame');

    tenterConnexion('AGENT.KOUAME', UserFactory::PIN);

    $this->assertAuthenticatedAs($user);
});

it('resets the failure counter and records the login time on success', function () {
    $user = utilisateurAvecRole(Role::Agent);
    User::query()->whereKey($user->id)->update(['tentatives_echouees' => 4]);

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN);

    expect($user->fresh())
        ->tentatives_echouees->toBe(0)
        ->derniere_connexion_le->not->toBeNull();
});

it('returns the same generic error for an unknown user and a wrong pin', function () {
    $user = utilisateurAvecRole(Role::Agent);

    $inconnu = tenterConnexion('personne', '12345');
    $mauvaisPin = tenterConnexion($user->nom_utilisateur, '00000');

    $inconnu->assertSessionHasErrors(['nom_utilisateur' => 'Identifiants incorrects.']);
    $mauvaisPin->assertSessionHasErrors(['nom_utilisateur' => 'Identifiants incorrects.']);
    $this->assertGuest();
});

it('only accepts a five-digit pin, for every account', function (string $saisie) {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    tenterConnexion($superadmin->nom_utilisateur, $saisie)->assertSessionHasErrors('password');

    $this->assertGuest();
    expect($superadmin->fresh()->tentatives_echouees)->toBe(0);
})->with([
    'trop court' => ['4815'],
    'trop long' => ['481573'],
    'mot de passe' => ['Motdepasse-Fort-2026'],
]);

it('counts each wrong pin as a failure', function () {
    $user = utilisateurAvecRole(Role::Agent);

    tenterConnexion($user->nom_utilisateur, '00000');
    tenterConnexion($user->nom_utilisateur, '00001');

    expect($user->fresh()->tentatives_echouees)->toBe(2);
});

it('locks the account once the failure threshold is reached', function () {
    config(['plateforme.connexion.echecs_avant_verrouillage' => 3]);
    $user = utilisateurAvecRole(Role::Agent);
    User::query()->whereKey($user->id)->update(['tentatives_echouees' => 2]);

    tenterConnexion($user->nom_utilisateur, '00000');

    expect($user->fresh()->estVerrouille())->toBeTrue()
        ->and(JournalAudit::where('action', 'compte.verrouille')->where('entite_id', $user->id)->count())->toBe(1);
});

it('refuses a locked account even with the correct pin and says so', function () {
    $user = utilisateurAvecRole(Role::Agent, ['verrouille_le' => now()]);

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN)
        ->assertSessionHasErrors(['nom_utilisateur' => 'Ce compte est verrouillé. Contactez un administrateur.']);

    $this->assertGuest();
});

it('does not reveal that an account is locked to someone with a wrong pin', function () {
    $user = utilisateurAvecRole(Role::Agent, ['verrouille_le' => now()]);

    tenterConnexion($user->nom_utilisateur, '00000')
        ->assertSessionHasErrors(['nom_utilisateur' => 'Identifiants incorrects.']);
});

it('refuses a deactivated account with the correct pin', function () {
    $user = User::factory()->inactif()->create();

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN)
        ->assertSessionHasErrors(['nom_utilisateur' => 'Ce compte est désactivé. Contactez un administrateur.']);

    $this->assertGuest();
});

it('treats a deleted account as unknown', function () {
    $user = utilisateurAvecRole(Role::Agent);
    $user->delete();

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN)
        ->assertSessionHasErrors(['nom_utilisateur' => 'Identifiants incorrects.']);
});

it('throttles repeated attempts on the same username', function () {
    $this->freezeTime();
    $user = utilisateurAvecRole(Role::Agent);

    foreach (range(1, 5) as $essai) {
        tenterConnexion($user->nom_utilisateur, '00000');
    }

    tenterConnexion($user->nom_utilisateur, UserFactory::PIN)
        ->assertSessionHasErrors(['nom_utilisateur' => 'Trop de tentatives de connexion. Réessayez dans 60 secondes.']);

    $this->assertGuest();
});

it('throttles one address trying many usernames', function () {
    config(['plateforme.connexion.tentatives_par_minute_par_ip' => 20]);
    $this->freezeTime();

    foreach (range(1, 20) as $essai) {
        tenterConnexion("compte{$essai}", '12345');
    }

    tenterConnexion('compte21', '12345')
        ->assertSessionHasErrors(['nom_utilisateur' => 'Trop de tentatives de connexion. Réessayez dans 60 secondes.']);
});

it('logs successes and failures without ever storing the pin', function () {
    $user = utilisateurAvecRole(Role::Agent);

    tenterConnexion($user->nom_utilisateur, '73916');
    tenterConnexion($user->nom_utilisateur, UserFactory::PIN);

    $journal = JournalAudit::query()->whereIn('action', ['connexion.echec', 'connexion.reussie'])->get();

    expect($journal->pluck('action')->all())->toBe(['connexion.echec', 'connexion.reussie'])
        ->and($journal->toJson())->not->toContain('73916')
        ->not->toContain(UserFactory::PIN);
});

it('logs out and invalidates the session', function () {
    $user = utilisateurAvecRole(Role::Agent);

    connecter($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});
