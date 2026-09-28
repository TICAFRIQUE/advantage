<?php

use App\Actions\Agent\ActiverCarteAction;
use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Exceptions\ActivationImpossibleException;
use App\Models\Carte;
use App\Models\Titulaire;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, string>  $surcharge
 * @return array{numero_carte: string, nom: string, prenom: string, telephone: string}
 */
function donneesActivation(array $surcharge = []): array
{
    return array_merge([
        'numero_carte' => '0000001',
        'nom' => 'KOUASSI',
        'prenom' => 'Aya Marie',
        'telephone' => '+2250707123456',
    ], $surcharge);
}

function activer(array $donnees): Carte
{
    $agent = utilisateurAvecRole(Role::Agent);
    test()->actingAs($agent);

    return app(ActiverCarteAction::class)($donnees, $agent);
}

it('activates a card for a new holder with a twelve month validity', function () {
    $this->freezeTime();

    $carte = activer(donneesActivation());

    expect($carte->fresh())
        ->statut->toBe(StatutCarte::Active)
        ->active_par_id->toBe(auth()->id())
        ->active_le->toDateTimeString()->toBe(now()->toDateTimeString())
        ->expire_le->toDateTimeString()->toBe(now()->addYear()->toDateTimeString())
        ->and($carte->titulaire)
        ->nom->toBe('KOUASSI')
        ->telephone->toBe('+2250707123456')
        ->cree_par_id->toBe(auth()->id());
});

it('reuses the holder found by phone number on renewal and updates the names', function () {
    $titulaire = Titulaire::factory()->create(['telephone' => '+2250707123456', 'nom' => 'ANCIEN']);
    Carte::factory()->for($titulaire)->expiree()->create();

    $carte = activer(donneesActivation(['numero_carte' => '0000002']));

    expect($carte->titulaire_id)->toBe($titulaire->id)
        ->and(Titulaire::count())->toBe(1)
        ->and($titulaire->fresh()->nom)->toBe('KOUASSI')
        ->and($titulaire->fresh()->modifie_par_id)->toBe(auth()->id());
});

it('refuses a card number that was already used', function (Closure $existante) {
    $existante();

    activer(donneesActivation());
})->with([
    'carte active' => [fn () => Carte::factory()->create(['numero_carte' => '0000001'])],
    'carte révoquée' => [fn () => Carte::factory()->revoquee()->create(['numero_carte' => '0000001'])],
    'carte expirée' => [fn () => Carte::factory()->expiree()->create(['numero_carte' => '0000001'])],
    'carte supprimée' => [fn () => Carte::factory()->create(['numero_carte' => '0000001'])->delete()],
])->throws(ActivationImpossibleException::class, 'déjà été activée');

it('refuses the second of two concurrent activations of the same number without leaving a holder behind', function () {
    // Un autre agent insère la même carte entre la vérification et l'insertion.
    $autre = Carte::factory()->create(['numero_carte' => '0000009']);
    DB::table('cartes')->where('id', $autre->id)->update(['numero_carte' => '0000008']);

    Carte::creating(function (Carte $carte) use ($autre): void {
        DB::table('cartes')->where('id', $autre->id)->update(['numero_carte' => $carte->numero_carte]);
    });

    $titulairesAvant = Titulaire::count();

    // La simulation s'exécute sur la même connexion : l'écriture concurrente est
    // annulée avec la transaction de l'action. On vérifie donc le refus propre
    // (message métier, pas d'erreur SQL) et l'absence de titulaire orphelin.
    expect(fn () => activer(donneesActivation(['numero_carte' => '0000001'])))
        ->toThrow(ActivationImpossibleException::class, 'déjà été activée')
        ->and(Titulaire::count())->toBe($titulairesAvant);
});

it('reuses the holder created at the same moment by another agent', function () {
    Titulaire::creating(function (Titulaire $titulaire): void {
        DB::table('titulaires')->insert([
            'nom' => 'CONCURRENT', 'prenom' => 'Agent', 'telephone' => $titulaire->telephone,
            'statut' => 'actif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    $carte = activer(donneesActivation());

    expect(Titulaire::count())->toBe(1)
        ->and($carte->titulaire->telephone)->toBe('+2250707123456');
});

it('refuses a holder who still has a card in circulation', function (Closure $etat) {
    $titulaire = Titulaire::factory()->create(['telephone' => '+2250707123456']);
    $etat(Carte::factory()->for($titulaire))->create(['numero_carte' => '1234567']);

    activer(donneesActivation());
})->with([
    'carte active' => [fn ($factory) => $factory],
    'carte suspendue' => [fn ($factory) => $factory->suspendue()],
])->throws(ActivationImpossibleException::class, 'possède déjà une carte active (123 456 7)');

it('allows a new card once the previous one was declared lost', function () {
    $titulaire = Titulaire::factory()->create(['telephone' => '+2250707123456']);
    Carte::factory()->for($titulaire)->revoquee()->create();

    expect(activer(donneesActivation())->titulaire_id)->toBe($titulaire->id);
});

it('marks an outdated active card as expired and allows the renewal', function () {
    $titulaire = Titulaire::factory()->create(['telephone' => '+2250707123456']);
    $ancienne = Carte::factory()->for($titulaire)->activeeIlYa(12, 2)->create();

    activer(donneesActivation());

    expect($ancienne->fresh()->statut)->toBe(StatutCarte::Expiree);
});
