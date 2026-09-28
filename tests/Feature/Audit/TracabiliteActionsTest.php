<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\JournalAudit;

it('logs every card consultation with its author', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $carte = Carte::factory()->create();

    connecter($agent)->get(route('gestion.cartes.show', $carte))->assertOk();

    expect(JournalAudit::where('action', 'carte.consultee')->sole())
        ->acteur_id->toBe($agent->id)
        ->entite_id->toBe($carte->id);
});

it('does not clutter the card history with consultations', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $carte = Carte::factory()->create();

    connecter($agent)->get(route('gestion.cartes.show', $carte));

    connecter($agent)->get(route('gestion.cartes.show', $carte))
        ->assertSee('Carte activée')
        ->assertDontSee('Carte consultée');
});

it('logs card searches with their criteria', function () {
    connecter(utilisateurAvecRole(Role::Agent))
        ->get(route('gestion.cartes.index', ['recherche' => 'konan', 'statut' => 'active']));

    expect(JournalAudit::where('action', 'cartes.recherchees')->sole()->donnees)
        ->toEqual(['recherche' => 'konan', 'statut' => 'active']);
});

it('does not log a plain listing without criteria', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.cartes.index'));

    expect(JournalAudit::where('action', 'cartes.recherchees')->exists())->toBeFalse();
});

it('logs holder lookups, found or not', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    connecter($agent)->postJson(route('gestion.titulaires.recherche'), ['telephone' => '0707123456']);

    expect(JournalAudit::where('action', 'titulaire.recherche')->sole())
        ->acteur_id->toBe($agent->id)
        ->donnees->toBe(['trouve' => false]);
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
        ->assertSee('Carte activée')
        ->assertDontSee('Carte vérifiée (partenaire)');

    expect(JournalAudit::where('action', 'carte.verifiee')->exists())->toBeTrue();
});
