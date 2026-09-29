<?php

use App\Actions\Droits\EnregistrerRoleAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\OperationRoleException;
use App\Models\JournalAudit;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\Droits\SynchroniserPermissions;
use Database\Seeders\RolesEtPermissionsSeeder;
use Tests\TestCase;

beforeEach(fn () => $this->seed(RolesEtPermissionsSeeder::class));

function confirmeRoles(User $user): TestCase
{
    return connecter($user)->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

function adminGestionnaireDesRoles(): User
{
    $admin = utilisateurAvecRole(Role::Admin);
    $admin->givePermissionTo(Permission::GererRoles->value);

    return $admin;
}

/**
 * @param  list<string>  $permissions
 */
function rolePersonnalise(string $libelle = 'Superviseur d\'agence', array $permissions = ['acceder-gestion', 'voir-tableau-de-bord', 'voir-cartes']): RoleUtilisateur
{
    return app(EnregistrerRoleAction::class)->creer($libelle, $permissions, utilisateurAvecRole(Role::Superadmin));
}

describe('matrice', function () {
    it('shows every role with its permissions to whoever manages roles', function () {
        rolePersonnalise('Superviseur');

        connecter(adminGestionnaireDesRoles())->get(route('gestion.roles.index'))
            ->assertOk()
            ->assertSeeInOrder(['Super administrateur', 'Administrateur', 'Agent', 'Superviseur', 'Partenaire'])
            ->assertSee('Personnalisé')
            ->assertSee(Permission::ActiverCarte->libelle())
            ->assertDontSee(Permission::RestaurerElements->libelle());
    });

    it('is reserved to the role management permission', function (Role $role) {
        connecter(utilisateurAvecRole($role))->get(route('gestion.roles.index'))->assertForbidden();
    })->with([Role::Admin, Role::Agent]);

    it('shows the menu entry to the superadmin', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.tableau-de-bord'))
            ->assertSee(route('gestion.roles.index'), false);
    });
});

describe('rôles personnalisés', function () {
    it('creates a back-office role with its permissions, and audits it', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        confirmeRoles($superadmin)->post(route('gestion.roles.store'), [
            'libelle' => 'Superviseur  d\'agence', 'permissions' => ['acceder-gestion', 'voir-tableau-de-bord', 'activer-carte'],
        ])->assertRedirect(route('gestion.roles.index'));

        $role = RoleUtilisateur::where('libelle', 'Superviseur d\'agence')->sole();

        expect($role)
            ->name->toBe('superviseur-dagence')
            ->espace->toBe('gestion')
            ->systeme->toBeFalse()
            ->cree_par_id->toBe($superadmin->id)
            ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['acceder-gestion', 'activer-carte', 'voir-tableau-de-bord'])
            ->and(JournalAudit::where('action', 'role.cree')->where('entite_id', $role->id)->exists())->toBeTrue()
            ->and(JournalAudit::where('action', 'permission.attribuee')->where('entite_id', $role->id)->exists())->toBeTrue();
    });

    it('asks for the password before creating a role', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.roles.store'), ['libelle' => 'Test', 'permissions' => []])
            ->assertRedirect(route('password.confirm'));

        expect(RoleUtilisateur::where('libelle', 'Test')->exists())->toBeFalse();
    });

    it('rejects a name already used, system roles included', function (string $libelle) {
        rolePersonnalise('Superviseur');

        confirmeRoles(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.roles.store'), ['libelle' => $libelle, 'permissions' => []])
            ->assertSessionHasErrors(['libelle' => 'Un rôle porte déjà ce nom.']);
    })->with(['Superviseur', 'administrateur']);

    it('gives a user with a custom role access to exactly its permissions', function () {
        $role = rolePersonnalise('Superviseur', ['acceder-gestion', 'voir-tableau-de-bord', 'voir-cartes']);
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        connecter($superadmin)->post(route('gestion.utilisateurs.store'), ['nom' => 'Aya Superviseure', 'nom_utilisateur' => 'aya.sup', 'role' => $role->name])
            ->assertSessionHasNoErrors();
        $compte = User::where('nom_utilisateur', 'aya.sup')->sole();

        expect($compte->rolePrincipal()->is($role))->toBeTrue()
            ->and($compte->libelleActeur())->toBe('Aya Superviseure · Superviseur')
            ->and($compte->estDuBackOffice())->toBeTrue();

        connecter($compte)->get(route('accueil-espace'))->assertRedirect(route('gestion.tableau-de-bord'));
        connecter($compte)->get(route('gestion.cartes.index'))->assertOk();
        connecter($compte)->get(route('gestion.cartes.create'))->assertForbidden();
        connecter($compte)->get(route('partenaire.tableau-de-bord'))->assertForbidden();
    });

    it('lets a custom role without back-office access in nowhere', function () {
        $role = rolePersonnalise('Sans accès', ['voir-cartes']);
        $compte = User::factory()->create();
        $compte->assignRole($role);

        connecter($compte)->get(route('gestion.cartes.index'))->assertForbidden();
    });

    it('renames a custom role, never a system role', function () {
        $role = rolePersonnalise('Superviseur');
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        confirmeRoles($superadmin)->put(route('gestion.roles.update', $role), ['libelle' => 'Chef d\'agence', 'permissions' => ['acceder-gestion']])
            ->assertSessionHas('succes');
        confirmeRoles($superadmin)->put(route('gestion.roles.update', RoleUtilisateur::findByName('agent')), ['libelle' => 'Agent renommé', 'permissions' => []])
            ->assertSessionHasErrors('libelle');

        expect($role->fresh()->libelle())->toBe('Chef d\'agence')
            ->and($role->fresh()->modifie_par_id)->toBe($superadmin->id)
            ->and(JournalAudit::where('action', 'role.renomme')->where('entite_id', $role->id)->exists())->toBeTrue()
            ->and(RoleUtilisateur::findByName('agent')->libelle())->toBe('Agent');
    });

    it('deletes an unused custom role only', function () {
        $role = rolePersonnalise('Temporaire');
        $utilise = rolePersonnalise('Utilisé');
        $compte = User::factory()->create();
        $compte->assignRole($utilise);
        $compte->delete();
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        confirmeRoles($superadmin)->delete(route('gestion.roles.destroy', $utilise))
            ->assertSessionHas('erreur', 'Ce rôle est encore attribué à des comptes : changez d\'abord leur rôle.');
        confirmeRoles($superadmin)->delete(route('gestion.roles.destroy', RoleUtilisateur::findByName('agent')))
            ->assertSessionHas('erreur', 'Un rôle système ne peut pas être supprimé.');
        confirmeRoles($superadmin)->delete(route('gestion.roles.destroy', $role))->assertSessionHas('succes');

        expect(RoleUtilisateur::whereKey($role->id)->exists())->toBeFalse()
            ->and(RoleUtilisateur::whereKey($utilise->id)->exists())->toBeTrue()
            ->and(JournalAudit::where('action', 'role.supprime')->where('entite_id', $role->id)->exists())->toBeTrue();
    });

    it('keeps custom roles through the permission sync and strips cross-space permissions', function () {
        $role = rolePersonnalise('Superviseur');
        $role->givePermissionTo(Permission::EffectuerTransaction->value);

        $rapport = app(SynchroniserPermissions::class)();

        expect(RoleUtilisateur::whereKey($role->id)->exists())->toBeTrue()
            ->and($rapport['incoherences_retirees'])->toContain('superviseur : effectuer-transaction')
            ->and($role->fresh()->hasPermissionTo(Permission::EffectuerTransaction->value))->toBeFalse();
    });
});

describe('permissions des rôles système', function () {
    it('lets the superadmin add and remove agent permissions, and audits both', function () {
        $agent = RoleUtilisateur::findByName('agent');
        $avant = $agent->permissions->pluck('name')->all();
        $demandees = array_values(array_diff([...$avant, 'exporter-donnees'], ['voir-rapport-cartes']));

        confirmeRoles(utilisateurAvecRole(Role::Superadmin))->put(route('gestion.roles.update', $agent), ['permissions' => $demandees])
            ->assertSessionHas('succes');

        expect($agent->fresh()->hasPermissionTo('exporter-donnees'))->toBeTrue()
            ->and($agent->fresh()->hasPermissionTo('voir-rapport-cartes'))->toBeFalse()
            ->and(JournalAudit::where('action', 'permission.attribuee')->where('entite_id', $agent->id)->latest('id')->first()->donnees['permissions'])->toBe(['exporter-donnees'])
            ->and(JournalAudit::where('action', 'permission.retiree')->where('entite_id', $agent->id)->latest('id')->first()->donnees['permissions'])->toBe(['voir-rapport-cartes']);
    });

    it('never mixes spaces, even for the superadmin', function () {
        $agent = RoleUtilisateur::findByName('agent');

        confirmeRoles(utilisateurAvecRole(Role::Superadmin))
            ->put(route('gestion.roles.update', $agent), ['permissions' => [...$agent->permissions->pluck('name'), 'effectuer-transaction']])
            ->assertSessionHas('erreur');

        expect($agent->fresh()->hasPermissionTo('effectuer-transaction'))->toBeFalse();
    });

    it('never grants a superadmin-only permission to a role', function () {
        expect(fn () => rolePersonnalise('Pirate', ['acceder-gestion', 'restaurer-elements']))
            ->toThrow(OperationRoleException::class);
    });

    it('does not open the superadmin role for edition', function () {
        confirmeRoles(utilisateurAvecRole(Role::Superadmin))
            ->get(route('gestion.roles.edit', RoleUtilisateur::findByName('superadmin')))
            ->assertNotFound();
    });
});

describe('anti-élévation de privilèges', function () {
    it('lets a delegated admin grant only permissions it holds', function () {
        $admin = adminGestionnaireDesRoles();

        confirmeRoles($admin)->post(route('gestion.roles.store'), ['libelle' => 'Pirate', 'permissions' => ['acceder-gestion', 'purger-journal-audit']])
            ->assertSessionHas('erreur');
        expect(RoleUtilisateur::where('libelle', 'Pirate')->exists())->toBeFalse();

        confirmeRoles($admin)->post(route('gestion.roles.store'), ['libelle' => 'Caisse centrale', 'permissions' => ['acceder-gestion', 'voir-cartes']])
            ->assertSessionHas('succes');
    });

    it('never lets an admin change its own role', function () {
        $admin = adminGestionnaireDesRoles();
        $role = RoleUtilisateur::findByName('admin');
        $avant = $role->permissions->pluck('name')->sort()->values()->all();

        confirmeRoles($admin)->put(route('gestion.roles.update', $role), ['permissions' => []])->assertSessionHas('succes');
        confirmeRoles($admin)->put(route('gestion.roles.update', $role), ['permissions' => [...$avant, 'purger-journal-audit']])->assertSessionHas('erreur');

        expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe($avant);
    });

    it('keeps the permissions an admin cannot modify when it edits a role', function () {
        $agent = RoleUtilisateur::findByName('agent');
        $agent->givePermissionTo('purger-journal-audit');
        $admin = adminGestionnaireDesRoles();

        confirmeRoles($admin)->put(route('gestion.roles.update', $agent), ['permissions' => ['acceder-gestion', 'voir-tableau-de-bord']])
            ->assertSessionHas('succes');

        expect($agent->fresh()->hasPermissionTo('purger-journal-audit'))->toBeTrue()
            ->and($agent->fresh()->hasPermissionTo('activer-carte'))->toBeFalse();
    });

    it('never lets an admin rename or delete a role more powerful than itself', function () {
        $role = rolePersonnalise('Auditeur', ['acceder-gestion', 'purger-journal-audit']);
        $admin = adminGestionnaireDesRoles();

        expect(GardeDroits::peutGererRole($admin, $role))->toBeFalse();
        confirmeRoles($admin)->delete(route('gestion.roles.destroy', $role))->assertSessionHas('erreur');
        expect(RoleUtilisateur::whereKey($role->id)->exists())->toBeTrue();
    });

    it('never lets an admin assign a custom role holding permissions it lacks, nor manage its holders', function () {
        $role = rolePersonnalise('Auditeur', ['acceder-gestion', 'purger-journal-audit']);
        $admin = utilisateurAvecRole(Role::Admin);
        $titulaire = User::factory()->create();
        $titulaire->assignRole($role);

        expect(collect(GardeDroits::rolesGestionAttribuables($admin))->pluck('name'))->not->toContain($role->name)
            ->and(GardeDroits::peutGererCompte($admin, $titulaire))->toBeFalse();

        connecter($admin)->post(route('gestion.utilisateurs.store'), ['nom_utilisateur' => 'faux.auditeur', 'role' => $role->name])
            ->assertSessionHasErrors('role');
    });
});

describe('formulaires', function () {
    it('renders the creation form with locked boxes for permissions the admin lacks', function () {
        $html = connecter(adminGestionnaireDesRoles())->get(route('gestion.roles.create'))
            ->assertOk()
            ->assertSee('Nouveau rôle')
            ->assertDontSee('permission-effectuer-transaction"', false)
            ->getContent();

        expect($html)->toMatch('/id="permission-purger-journal-audit"[^>]*\sdisabled/')
            ->not->toMatch('/id="permission-activer-carte"[^>]*\sdisabled/');
    });

    it('renders the edit form of a system role without a name field', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.roles.edit', RoleUtilisateur::findByName('agent')))
            ->assertOk()
            ->assertSee('Modifier le rôle Agent')
            ->assertDontSee('id="libelle"', false)
            ->assertDontSee('Supprimer le rôle');
    });

    it('renders the edit form of a custom role with rename and delete', function () {
        $role = rolePersonnalise('Superviseur');

        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.roles.edit', $role))
            ->assertOk()
            ->assertSee('value="Superviseur"', false)
            ->assertSee('Supprimer le rôle');
    });
});
