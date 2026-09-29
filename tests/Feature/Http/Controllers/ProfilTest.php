<?php

use App\Enums\Role;
use App\Models\JournalAudit;

it('shows my own information and my recent activity, from the account menu', function (Role $role) {
    $compte = utilisateurAvecRole($role, ['nom' => 'Awa Koné', 'nom_utilisateur' => 'awa.kone', 'telephone' => '+2250707123456']);

    connecter($compte)->get(route('profil'))
        ->assertOk()
        ->assertSee('Awa Koné')
        ->assertSee('@awa.kone')
        ->assertSee('+225 07 07 12 34 56')
        ->assertSee('Mon activité récente')
        ->assertDontSee('Mes droits')
        ->assertSee('href="'.route('profil').'"', false);
})->with([
    'agent' => [Role::Agent],
    'utilisateur de partenaire' => [Role::Partenaire],
]);

it('lists my 20 most recent actions only, newest first', function () {
    $compte = utilisateurAvecRole(Role::Agent);
    $autre = utilisateurAvecRole(Role::Agent);

    foreach (range(1, 21) as $i) {
        JournalAudit::create(['acteur_id' => $compte->id, 'type_acteur' => 'utilisateur', 'action' => 'carte.activee', 'donnees' => ['numero_carte' => sprintf('900%04d', $i)]]);
        $this->travel(1)->minutes();
    }
    JournalAudit::create(['acteur_id' => $autre->id, 'type_acteur' => 'utilisateur', 'action' => 'partenaire.cree', 'donnees' => ['nom' => 'Pharmacie voisine']]);

    connecter($compte)->get(route('profil'))
        ->assertSee('Carte activée')
        ->assertSeeInOrder(['9000021', '9000020', '9000002'])
        ->assertDontSee('9000001')
        ->assertDontSee('Partenaire créé')
        ->assertDontSee('Pharmacie voisine');
});

it('tells when there is no recent activity', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('profil'))
        ->assertSee('Aucune activité enregistrée récemment.');
});

it('tells the superadmin where his password lives', function () {
    connecter(utilisateurAvecRole(Role::Superadmin))->get(route('profil'))
        ->assertOk()
        ->assertSee('SUPERADMIN_MOT_DE_PASSE');
});

it('requires to be logged in', function () {
    $this->get(route('profil'))->assertRedirect(route('login'));
});

it('explains why an account cannot be managed instead of a misleading message', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);
    $autre = utilisateurAvecRole(Role::Superadmin);
    $admin = utilisateurAvecRole(Role::Admin);

    connecter($superadmin)->get(route('gestion.utilisateurs.show', $superadmin))
        ->assertSee("C'est votre compte", false)
        ->assertSee(route('profil'), false)
        ->assertDontSee('rang supérieur ou égal');

    connecter($superadmin)->get(route('gestion.utilisateurs.show', $autre))
        ->assertSee('Compte super administrateur')
        ->assertDontSee('rang supérieur ou égal');

    connecter($admin)->get(route('gestion.utilisateurs.show', utilisateurAvecRole(Role::Admin)))
        ->assertSee('rang supérieur ou égal');

    // Le superadmin gère bien un admin : aucun message de blocage.
    connecter($superadmin)->get(route('gestion.utilisateurs.show', $admin))
        ->assertSee(route('gestion.comptes.pin', $admin), false)
        ->assertDontSee('bi-lock me-1', false);
});
