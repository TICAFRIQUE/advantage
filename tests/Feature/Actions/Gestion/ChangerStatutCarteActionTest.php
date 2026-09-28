<?php

use App\Actions\Gestion\ChangerStatutCarteAction;
use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Enums\StatutDemandeOtp;
use App\Exceptions\ActionCarteImpossibleException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;

function changerStatut(Carte $carte, StatutCarte $cible, string $motif = 'Contrôle'): Carte
{
    test()->actingAs(utilisateurAvecRole(Role::Admin));

    return app(ChangerStatutCarteAction::class)($carte, $cible, $motif);
}

it('suspends an active card and cancels its pending codes', function () {
    $carte = Carte::factory()->create();
    $demande = DemandeOtp::factory()->create(['carte_id' => $carte->id]);

    changerStatut($carte, StatutCarte::Suspendue, 'Litige en cours');

    expect($carte->fresh())
        ->statut->toBe(StatutCarte::Suspendue)
        ->motif_statut->toBe('Suspension : Litige en cours')
        ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Expiree);
});

it('reactivates a suspended card', function () {
    $carte = Carte::factory()->suspendue()->create();

    changerStatut($carte, StatutCarte::Active, 'Litige réglé');

    expect($carte->fresh()->statut)->toBe(StatutCarte::Active)
        ->and($carte->fresh()->estUtilisable())->toBeTrue();
});

it('revokes a lost card from active or suspended', function (Closure $fabriquer) {
    $carte = $fabriquer();

    changerStatut($carte, StatutCarte::Revoquee, 'Carte perdue');

    expect($carte->fresh())
        ->statut->toBe(StatutCarte::Revoquee)
        ->motif_statut->toBe('Révocation : Carte perdue');
})->with([
    'active' => [fn () => Carte::factory()->create()],
    'suspendue' => [fn () => Carte::factory()->suspendue()->create()],
]);

it('refuses any change on a final card', function (Closure $fabriquer, StatutCarte $cible) {
    changerStatut($fabriquer(), $cible);
})->with([
    'révoquée → active' => [fn () => Carte::factory()->revoquee()->create(), StatutCarte::Active],
    'révoquée → suspendue' => [fn () => Carte::factory()->revoquee()->create(), StatutCarte::Suspendue],
    'expirée → active' => [fn () => Carte::factory()->expiree()->create(), StatutCarte::Active],
    'date échue → suspendue' => [fn () => Carte::factory()->activeeIlYa(12, 1)->create(), StatutCarte::Suspendue],
])->throws(ActionCarteImpossibleException::class);

it('never reactivates a suspended card whose validity has ended', function () {
    $carte = Carte::factory()->activeeIlYa(12, 1)->suspendue()->create();

    expect($carte->transitionsPossibles())->toBe([StatutCarte::Revoquee])
        ->and(fn () => changerStatut($carte, StatutCarte::Active))->toThrow(ActionCarteImpossibleException::class);
});

it('refuses a transition to the current status', function () {
    changerStatut(Carte::factory()->create(), StatutCarte::Active);
})->throws(ActionCarteImpossibleException::class);

it('logs the change with its author and reason', function () {
    $carte = Carte::factory()->create();

    changerStatut($carte, StatutCarte::Suspendue, 'Vérification');

    $entree = JournalAudit::where('action', 'carte.statut_modifie')->where('entite_id', $carte->id)->sole();

    expect($entree->acteur_id)->toBe(auth()->id())
        ->and($entree->donnees['apres']['motif_statut'])->toBe('Suspension : Vérification');
});
