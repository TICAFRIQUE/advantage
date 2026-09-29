<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\JournalAudit;

it('no longer logs mere consultations: card detail, card search, holder lookup', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $carte = Carte::factory()->create();
    $avant = JournalAudit::count();

    connecter($agent)->get(route('gestion.cartes.show', $carte))->assertOk();
    connecter($agent)->get(route('gestion.cartes.index', ['recherche' => 'konan', 'statut' => 'active']))->assertOk();
    connecter($agent)->postJson(route('gestion.titulaires.recherche'), ['telephone' => '0707123456'])->assertOk();

    expect(JournalAudit::count())->toBe($avant)
        ->and(JournalAudit::whereIn('action', ['carte.consultee', 'cartes.recherchees', 'titulaire.recherche'])->exists())->toBeFalse();
});

it('does not clutter the card history with consultations', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $carte = Carte::factory()->create();

    connecter($agent)->get(route('gestion.cartes.show', $carte));

    connecter($agent)->get(route('gestion.cartes.show', $carte))
        ->assertSeeInOrder(['Historique des opérations', 'Activation'])
        ->assertDontSee('Carte consultée');
});

it('logs logouts', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    connecter($agent)->post(route('logout'));

    expect(JournalAudit::where('action', 'deconnexion')->sole()->acteur_id)->toBe($agent->id);
});

it('keeps partner verifications out of the card operations history', function () {
    $carte = Carte::factory()->create(['numero_carte' => '4567890']);
    connecter(utilisateurAvecRole(Role::Partenaire))
        ->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '4567890']);

    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.show', $carte))
        ->assertSeeInOrder(['Historique des opérations', 'Activation'])
        ->assertDontSee('Carte vérifiée (partenaire)');

    expect(JournalAudit::where('action', 'carte.verifiee')->exists())->toBeTrue();
});

it('records readable data for logins and card activations', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom_utilisateur' => 'yao.agent']);
    $agent->forceFill(['password' => '24680'])->save();

    formulaireConnexionAffiche()->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/140.0 Safari/537.36')
        ->post(route('login.store'), ['nom_utilisateur' => 'yao.agent', 'password' => '24680']);

    expect(JournalAudit::where('action', 'connexion.reussie')->sole()->donnees)
        ->toEqual(['nom_utilisateur' => 'yao.agent', 'navigateur' => 'Chrome 140 · Windows']);

    $carte = Carte::factory()->create(['numero_carte' => '1234567']);

    expect(JournalAudit::where('action', 'carte.activee')->where('entite_id', $carte->id)->sole()->donnees['apres'])
        ->toMatchArray(['numero_carte' => '123 456 7', 'titulaire' => $carte->titulaire->nomComplet()]);
});
