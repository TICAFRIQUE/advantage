<?php

use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Enums\TypeOperationCarte;
use App\Models\Carte;
use App\Models\OperationCarte;
use App\Models\Titulaire;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function donneesRapportCartes(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.cartes.rapport.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 50], $parametres)))
        ->assertOk()
        ->json();
}

it('counts the operations by type', function () {
    $carte = Carte::factory()->create();
    Carte::factory()->create();
    Auth::login(utilisateurAvecRole(Role::Admin));
    $carte->update(['statut' => StatutCarte::Suspendue, 'motif_statut' => 'Suspension : contrôle']);
    $carte->update(['statut' => StatutCarte::Active, 'motif_statut' => 'Réactivation : ok']);
    $carte->update(['statut' => StatutCarte::Revoquee, 'motif_statut' => 'Révocation : perte']);
    Auth::logout();

    $indicateurs = connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.rapport'))->assertOk()->viewData('indicateurs');

    expect($indicateurs)->toMatchArray([
        'total' => 5,
        'activation' => 2,
        'suspension' => 1,
        'reactivation' => 1,
        'revocation' => 1,
        'expiration' => 0,
    ]);
});

it('filters on the date of every operation, not only activations', function () {
    $ancienne = Carte::factory()->activeeIlYa(3)->create();
    Carte::factory()->activeeIlYa(2)->create();
    Auth::login(utilisateurAvecRole(Role::Admin));
    $ancienne->update(['statut' => StatutCarte::Suspendue, 'motif_statut' => 'Suspension : contrôle']);
    Auth::logout();

    $admin = utilisateurAvecRole(Role::Admin);
    $filtres = ['du' => now()->toDateString(), 'au' => now()->toDateString()];
    $donnees = donneesRapportCartes($admin, $filtres);

    expect($donnees['recordsFiltered'])->toBe(1)
        ->and($donnees['data'][0]['operation'])->toBe('Suspension')
        ->and($donnees['data'][0]['motif'])->toBe('Suspension : contrôle')
        ->and(connecter($admin)->get(route('gestion.cartes.rapport', $filtres))->viewData('indicateurs')['total'])->toBe(1);
});

it('filters on the type and the author of the operation', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Filtre']);
    Carte::factory()->for($agent, 'activePar')->create();
    Carte::factory()->create();

    $admin = utilisateurAvecRole(Role::Admin);

    expect(donneesRapportCartes($admin, ['agent_id' => $agent->id, 'type' => 'activation'])['recordsFiltered'])->toBe(1)
        ->and(donneesRapportCartes($admin, ['type' => 'suspension'])['recordsFiltered'])->toBe(0);
});

it('lets an agent see all operations or only its own', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    Carte::factory()->for($agent, 'activePar')->create();
    Carte::factory()->create();

    expect(donneesRapportCartes($agent)['recordsFiltered'])->toBe(2)
        ->and(donneesRapportCartes($agent, ['mes_operations' => 1])['recordsFiltered'])->toBe(1);
});

it('no longer ranks activations by agent', function () {
    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.rapport'))
        ->assertOk()
        ->assertDontSee('Activations par agent');
});

it('attributes automatic expirations to the system', function () {
    $carte = Carte::factory()->activeeIlYa(13)->create();
    Auth::login(utilisateurAvecRole(Role::Agent));
    $carte->update(['statut' => StatutCarte::Expiree, 'motif_statut' => 'Date de validité dépassée']);
    Auth::logout();

    $ligne = donneesRapportCartes(utilisateurAvecRole(Role::Admin), ['type' => 'expiration'])['data'][0];

    expect($ligne['effectuee_par'])->toBe('Système');
});

it('records holder changes without the phone number', function () {
    $carte = Carte::factory()->for(Titulaire::factory()->create(['nom' => 'ANCIEN']))->create();

    connecter(utilisateurAvecRole(Role::Admin))
        ->put(route('gestion.cartes.titulaire.update', $carte), ['nom' => 'KOUASSI', 'prenom' => $carte->titulaire->prenom]);

    expect(OperationCarte::where('type', TypeOperationCarte::ModificationTitulaire)->sole())
        ->carte_id->toBe($carte->id)
        ->motif->toBe('Modifié : nom');
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

it('keeps the history append-only', function () {
    Carte::factory()->create();

    expect(fn () => OperationCarte::sole()->update(['motif' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => OperationCarte::sole()->delete())->toThrow(LogicException::class);
});

it('rejects inconsistent filters', function () {
    connecter(utilisateurAvecRole(Role::Admin))
        ->get(route('gestion.cartes.rapport', ['du' => '2026-09-10', 'au' => '2026-09-01', 'type' => 'inconnu']))
        ->assertSessionHasErrors(['au', 'type']);
});

it('forbids the report without the permission', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    Spatie\Permission\Models\Role::findByName('agent')->revokePermissionTo('voir-rapport-cartes');

    connecter($agent->fresh())->get(route('gestion.cartes.rapport'))->assertForbidden();
});
