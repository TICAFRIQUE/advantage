<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Services\Droits\GardeDroits;

describe('permissions des rôles', function () {
    it('lets the superadmin edit admin and agent roles', function (Role $role) {
        expect(GardeDroits::peutModifierPermissionDuRole(utilisateurAvecRole(Role::Superadmin), $role, Permission::ExporterDonnees))->toBeTrue();
    })->with([Role::Admin, Role::Agent]);

    it('never lets anyone edit the superadmin role', function () {
        expect(GardeDroits::peutModifierPermissionDuRole(utilisateurAvecRole(Role::Superadmin), Role::Superadmin, Permission::ExporterDonnees))->toBeFalse();
    });

    it('never mixes spaces', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        expect(GardeDroits::peutModifierPermissionDuRole($superadmin, Role::Agent, Permission::EffectuerTransaction))->toBeFalse()
            ->and(GardeDroits::peutModifierPermissionDuRole($superadmin, Role::Partenaire, Permission::ActiverCarte))->toBeFalse();
    });

    it('refuses an admin without the role management right', function () {
        expect(GardeDroits::peutModifierPermissionDuRole(utilisateurAvecRole(Role::Admin), Role::Agent, Permission::ActiverCarte))->toBeFalse();
    });

    it('lets a delegated admin grant only permissions it holds, never on its own role', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $admin->givePermissionTo('gerer-roles');

        expect(GardeDroits::peutModifierPermissionDuRole($admin, Role::Agent, Permission::ActiverCarte))->toBeTrue()
            ->and(GardeDroits::peutModifierPermissionDuRole($admin, Role::Agent, Permission::PurgerJournalAudit))->toBeFalse()
            ->and(GardeDroits::peutModifierPermissionDuRole($admin, Role::Admin, Permission::ActiverCarte))->toBeFalse();
    });
});

describe('attribution des rôles', function () {
    it('reserves admin and superadmin roles to the superadmin', function (Role $role) {
        $cible = utilisateurAvecRole(Role::Agent);

        expect(GardeDroits::peutAttribuerRole(utilisateurAvecRole(Role::Admin), $cible, $role))->toBeFalse()
            ->and(GardeDroits::peutAttribuerRole(utilisateurAvecRole(Role::Superadmin), $cible, $role))->toBeTrue();
    })->with([Role::Admin, Role::Superadmin]);

    it('lets an admin create agents', function () {
        expect(GardeDroits::peutAttribuerRole(utilisateurAvecRole(Role::Admin), User::factory()->create(), Role::Agent))->toBeTrue();
    });

    it('never lets anyone change their own roles', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        expect(GardeDroits::peutAttribuerRole($superadmin, $superadmin, Role::Admin))->toBeFalse();
    });
});

describe('gestion des comptes', function () {
    it('protects superadmins and one\'s own account', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        expect(GardeDroits::peutGererCompte($superadmin, utilisateurAvecRole(Role::Superadmin)))->toBeFalse()
            ->and(GardeDroits::peutGererCompte($superadmin, $superadmin))->toBeFalse();
    });

    it('lets an admin manage agents but not other admins', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        expect(GardeDroits::peutGererCompte($admin, utilisateurAvecRole(Role::Agent)))->toBeTrue()
            ->and(GardeDroits::peutGererCompte($admin, utilisateurAvecRole(Role::Admin)))->toBeFalse();
    });

    it('requires the operators right to manage partner accounts', function () {
        expect(GardeDroits::peutGererCompte(utilisateurAvecRole(Role::Agent), utilisateurAvecRole(Role::Partenaire)))->toBeFalse()
            ->and(GardeDroits::peutGererCompte(utilisateurAvecRole(Role::Admin), utilisateurAvecRole(Role::Partenaire)))->toBeTrue();
    });
});
