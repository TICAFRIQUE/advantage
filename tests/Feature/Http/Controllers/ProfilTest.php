<?php

use App\Enums\Permission;
use App\Enums\Role;

it('shows my own information and rights, from the account menu', function (Role $role, Permission $droit) {
    $compte = utilisateurAvecRole($role, ['nom' => 'Awa Koné', 'nom_utilisateur' => 'awa.kone', 'telephone' => '+2250707123456']);

    connecter($compte)->get(route('profil'))
        ->assertOk()
        ->assertSee('Awa Koné')
        ->assertSee('@awa.kone')
        ->assertSee('+225 07 07 12 34 56')
        ->assertSee('Mes droits')
        ->assertSee($droit->libelle())
        ->assertSee('href="'.route('profil').'"', false);
})->with([
    'agent' => [Role::Agent, Permission::ActiverCarte],
    'utilisateur de partenaire' => [Role::Partenaire, Permission::EffectuerTransaction],
]);

it('lists only the rights I hold', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('profil'))
        ->assertDontSee(Permission::GererRoles->libelle())
        ->assertDontSee(Permission::PurgerJournalAudit->libelle());
});

it('tells the superadmin he holds every right and where his password lives', function () {
    connecter(utilisateurAvecRole(Role::Superadmin))->get(route('profil'))
        ->assertOk()
        ->assertSee('vous disposez de tous les droits')
        ->assertSee('SUPERADMIN_MOT_DE_PASSE')
        ->assertSee(Permission::RestaurerElements->libelle());
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
