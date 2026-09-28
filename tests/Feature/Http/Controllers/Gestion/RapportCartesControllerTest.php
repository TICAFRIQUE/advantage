<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\Titulaire;
use App\Models\User;

function donneesRapportCartes(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.cartes.rapport.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 50], $parametres)))
        ->assertOk()
        ->json();
}

it('computes the indicators on the effective status', function () {
    Carte::factory()->create();
    Carte::factory()->activeeIlYa(11, 15)->create(); // expire dans ~15 jours
    Carte::factory()->activeeIlYa(12, 2)->create(); // date échue : expirée
    Carte::factory()->suspendue()->create();
    Carte::factory()->revoquee()->create();

    $reponse = connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.rapport'))->assertOk();

    expect($reponse->viewData('indicateurs'))->toBe([
        'total' => 5, 'actives' => 2, 'suspendues' => 1, 'revoquees' => 1, 'expirees' => 1, 'expirent_sous_30_jours' => 1,
    ]);
});

it('applies the same filters to indicators and list', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Filtre']);
    Carte::factory()->for($agent, 'activePar')->create();
    Carte::factory()->for($agent, 'activePar')->suspendue()->create();
    Carte::factory()->create();

    $filtres = ['agent_id' => $agent->id, 'statut' => 'suspendue'];
    $admin = utilisateurAvecRole(Role::Admin);

    expect(connecter($admin)->get(route('gestion.cartes.rapport', $filtres))->viewData('indicateurs')['total'])->toBe(1)
        ->and(donneesRapportCartes($admin, $filtres)['recordsFiltered'])->toBe(1);
});

it('filters on the activation period', function () {
    Carte::factory()->activeeIlYa(3)->create();
    Carte::factory()->create();

    $donnees = donneesRapportCartes(utilisateurAvecRole(Role::Admin), ['du' => now()->subDays(7)->toDateString(), 'au' => now()->toDateString()]);

    expect($donnees['recordsFiltered'])->toBe(1);
});

it('lets an agent see all cards or only its activations', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    Carte::factory()->for($agent, 'activePar')->create();
    Carte::factory()->create();

    expect(donneesRapportCartes($agent)['recordsFiltered'])->toBe(2)
        ->and(donneesRapportCartes($agent, ['mes_activations' => 1])['recordsFiltered'])->toBe(1);
});

it('ranks activations by agent', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Top Agent']);
    Carte::factory(3)->for($agent, 'activePar')->create();

    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.rapport'))
        ->assertSeeInOrder(['Activations par agent', 'Top Agent', '3']);
});

it('searches by name without a digit matching every card', function () {
    Carte::factory()->for(Titulaire::factory()->create(['nom' => 'KONAN']))->create();
    Carte::factory()->for(Titulaire::factory()->create(['nom' => 'TRAORE']))->create();

    $donnees = donneesRapportCartes(utilisateurAvecRole(Role::Admin), ['search' => ['value' => 'konan']]);

    expect($donnees['recordsFiltered'])->toBe(1);
});

it('links each row to the card and escapes values', function () {
    $carte = Carte::factory()->for(Titulaire::factory()->create(['nom' => '<b>X</b>']))->create();

    $ligne = donneesRapportCartes(utilisateurAvecRole(Role::Admin))['data'][0];

    expect($ligne['lien'])->toBe(route('gestion.cartes.show', $carte))
        ->and($ligne['titulaire'])->not->toContain('<b>');
});

it('rejects inconsistent filters', function () {
    connecter(utilisateurAvecRole(Role::Admin))
        ->get(route('gestion.cartes.rapport', ['du' => '2026-09-10', 'au' => '2026-09-01', 'statut' => 'inconnu']))
        ->assertSessionHasErrors(['au', 'statut']);
});

it('forbids the report without the permission', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    Spatie\Permission\Models\Role::findByName('agent')->revokePermissionTo('voir-rapport-cartes');

    connecter($agent->fresh())->get(route('gestion.cartes.rapport'))->assertForbidden();
});
