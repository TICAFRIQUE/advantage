<?php

use App\Enums\Role;
use App\Enums\StatutUtilisateur;
use App\Models\User;

it('logs out a user whose account gets locked during the session', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    User::query()->whereKey($agent->id)->update(['verrouille_le' => now()]);

    connecter($agent->fresh())->get(route('gestion.tableau-de-bord'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('nom_utilisateur');

    $this->assertGuest();
});

it('logs out a user whose account gets deactivated during the session', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $agent->forceFill(['statut' => StatutUtilisateur::Inactif])->save();

    connecter($agent)->get(route('gestion.tableau-de-bord'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('logs out a session older than the maximum duration', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    $this->actingAs($agent)
        ->withSession(['connecte_le' => now()->subMinutes(481)->getTimestamp()])
        ->get(route('gestion.tableau-de-bord'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('logs out a session with no recorded login time', function () {
    $this->actingAs(utilisateurAvecRole(Role::Agent))
        ->get(route('gestion.tableau-de-bord'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps a session within its maximum duration', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    $this->actingAs($agent)
        ->withSession(['connecte_le' => now()->subMinutes(479)->getTimestamp()])
        ->get(route('gestion.tableau-de-bord'))
        ->assertOk();
});
