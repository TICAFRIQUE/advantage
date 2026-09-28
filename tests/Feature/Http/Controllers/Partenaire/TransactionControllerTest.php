<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Titulaire;
use App\Models\Transaction;
use App\Models\User;

function transactionPour(User $operateur, array $titulaire = [], string $valideeLe = 'now'): Transaction
{
    $carte = Carte::factory()->for(Titulaire::factory()->create($titulaire))->create();
    $demande = DemandeOtp::factory()->utilisee()->create(['carte_id' => $carte->id, 'partenaire_id' => $operateur->partenaire_id]);

    return Transaction::factory()->create(['demande_otp_id' => $demande->id, 'validee_le' => $valideeLe === 'now' ? now() : $valideeLe]);
}

function donneesTableau(User $operateur, array $parametres = []): array
{
    return connecter($operateur)
        ->getJson(route('partenaire.transactions.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 25], $parametres)))
        ->assertOk()
        ->json();
}

it('lists only the transactions of the current partner', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'KONAN']);
    transactionPour(utilisateurAvecRole(Role::Partenaire), ['nom' => 'AUTRE']);

    $donnees = donneesTableau($operateur);

    expect($donnees['recordsTotal'])->toBe(1)
        ->and($donnees['data'][0]['titulaire'])->toContain('KONAN');
});

it('filters by period on the server', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'ANCIEN'], now()->subDays(40)->toDateTimeString());
    transactionPour($operateur, ['nom' => 'RECENT']);

    $donnees = donneesTableau($operateur, ['du' => now()->subDays(7)->toDateString(), 'au' => now()->toDateString()]);

    expect($donnees['recordsFiltered'])->toBe(1)
        ->and($donnees['data'][0]['titulaire'])->toContain('RECENT');
});

it('searches by holder name on the server', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'KONAN']);
    transactionPour($operateur, ['nom' => 'TRAORE']);

    $donnees = donneesTableau($operateur, ['search' => ['value' => 'kon']]);

    expect($donnees['recordsFiltered'])->toBe(1);
});

it('escapes values to prevent script injection', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => '<script>alert(1)</script>']);

    expect(donneesTableau($operateur)['data'][0]['titulaire'])->not->toContain('<script>');
});

it('rejects an inconsistent period', function () {
    connecter(utilisateurAvecRole(Role::Partenaire))
        ->getJson(route('partenaire.transactions.donnees', ['du' => '2026-09-10', 'au' => '2026-09-01']))
        ->assertUnprocessable();
});

it('forbids agents from reading partner transactions', function () {
    connecter(utilisateurAvecRole(Role::Agent))->getJson(route('partenaire.transactions.donnees'))->assertForbidden();
});
