<?php

use App\Enums\Role;
use App\Models\JournalAudit;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

it('generates a new pin, unlocks the account and resets the failure counter', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    User::query()->whereKey($agent->id)->update(['tentatives_echouees' => 10, 'verrouille_le' => now()]);

    $this->artisan('utilisateur:reinitialiser-pin', ['nom_utilisateur' => strtoupper($agent->nom_utilisateur)])
        ->expectsOutputToContain('Nouveau PIN')
        ->assertSuccessful();

    $agent->refresh();
    $pin = JournalAudit::where('action', 'utilisateur.pin_reinitialise')->exists();

    expect($agent->estVerrouille())->toBeFalse()
        ->and($agent->tentatives_echouees)->toBe(0)
        ->and(Hash::check(UserFactory::PIN, $agent->password))->toBeFalse()
        ->and($pin)->toBeTrue()
        ->and(JournalAudit::where('action', 'compte.deverrouille')->exists())->toBeTrue();
});

it('refuses an unknown account', function () {
    $this->artisan('utilisateur:reinitialiser-pin', ['nom_utilisateur' => 'inconnu'])->assertFailed();
});

it('resets the superadmin pin too, from the server only', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    $this->artisan('utilisateur:reinitialiser-pin', ['nom_utilisateur' => $superadmin->nom_utilisateur])
        ->expectsOutputToContain('Nouveau PIN')
        ->assertSuccessful();

    expect(Hash::check(UserFactory::PIN, $superadmin->fresh()->password))->toBeFalse();
});
