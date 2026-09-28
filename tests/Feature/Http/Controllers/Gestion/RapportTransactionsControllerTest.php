<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;

function passage(Partenaire $partenaire, ?Carte $carte = null, float $taux = 10, string $valideeLe = 'now'): Transaction
{
    $demande = DemandeOtp::factory()->utilisee()->create([
        'carte_id' => ($carte ?? Carte::factory()->create())->id,
        'partenaire_id' => $partenaire->id,
    ]);

    return Transaction::factory()->create([
        'demande_otp_id' => $demande->id,
        'taux_applique' => $taux,
        'validee_le' => $valideeLe === 'now' ? now() : $valideeLe,
    ]);
}

function donneesRapportTransactions(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.transactions.rapport.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 50], $parametres)))
        ->assertOk()
        ->json();
}

it('computes passages, distinct cards and partners and the average rate', function () {
    $pharmacie = Partenaire::factory()->create();
    $hotel = Partenaire::factory()->create();
    $carte = Carte::factory()->create();
    passage($pharmacie, $carte, 10);
    passage($pharmacie, $carte, 10);
    passage($hotel, null, 20);

    $indicateurs = connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.transactions.rapport'))->viewData('indicateurs');

    expect($indicateurs)->toBe([
        'passages' => 3, 'cartes_distinctes' => 2, 'partenaires_distincts' => 2, 'taux_moyen' => '13,33 %',
    ]);
});

it('filters by partner and period, identically for indicators and list', function () {
    $pharmacie = Partenaire::factory()->create();
    passage($pharmacie);
    passage($pharmacie, valideeLe: now()->subDays(40)->toDateTimeString());
    passage(Partenaire::factory()->create());

    $filtres = ['partenaire_id' => $pharmacie->id, 'du' => now()->subDays(7)->toDateString(), 'au' => now()->toDateString()];
    $admin = utilisateurAvecRole(Role::Admin);

    expect(connecter($admin)->get(route('gestion.transactions.rapport', $filtres))->viewData('indicateurs')['passages'])->toBe(1)
        ->and(donneesRapportTransactions($admin, $filtres)['recordsFiltered'])->toBe(1);
});

it('no longer ranks passages by partner', function () {
    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.transactions.rapport'))
        ->assertOk()
        ->assertDontSee('Passages par partenaire');
});

it('searches by partner name on the server', function () {
    passage(Partenaire::factory()->create(['nom' => 'Pharmacie Lagune']));
    passage(Partenaire::factory()->create(['nom' => 'Hôtel Ivoire']));

    expect(donneesRapportTransactions(utilisateurAvecRole(Role::Admin), ['search' => ['value' => 'lagune']])['recordsFiltered'])->toBe(1);
});

it('reserves the report to accounts with the permission', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.transactions.rapport'))->assertForbidden();
});
