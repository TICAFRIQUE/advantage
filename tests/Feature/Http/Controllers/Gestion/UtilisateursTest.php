<?php

use App\Actions\Comptes\CreerCompteAction;
use App\Actions\Comptes\GererCompteAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\OperationCompteException;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as ModeleRole;
use Tests\TestCase;

function donneesUtilisateurs(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.utilisateurs.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 50], $parametres)))
        ->assertOk()
        ->json();
}

function confirme(User $user): TestCase
{
    return connecter($user)->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

describe('liste', function () {
    it('lists back-office accounts only, with their creator', function () {
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);
        $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Yao Agent', 'cree_par_id' => $admin->id]);
        utilisateurAvecRole(Role::Partenaire, ['nom' => 'Caissier Pharmacie']);

        $lignes = collect(donneesUtilisateurs($admin)['data']);

        expect($lignes->pluck('nom'))->toContain('Yao Agent', 'Awa Koné')->not->toContain('Caissier Pharmacie')
            ->and($lignes->firstWhere('nom', 'Yao Agent'))
            ->role->toBe('Agent')
            ->etat->toBe('Actif')
            ->cree_par->toBe('Awa Koné · Administrateur')
            ->lien->toBe(route('gestion.utilisateurs.show', $agent));
    });

    it('filters by role, by state and searches by name or username', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Actif']);
        utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Verrouillé', 'verrouille_le' => now()]);
        utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Désactivé', 'statut' => 'inactif', 'nom_utilisateur' => 'yao.inactif']);

        $noms = fn (array $parametres) => collect(donneesUtilisateurs($admin, $parametres)['data'])->pluck('nom')->sort()->values()->all();

        expect($noms(['role' => 'agent', 'etat' => 'actif']))->toBe(['Agent Actif'])
            ->and($noms(['etat' => 'verrouille']))->toBe(['Agent Verrouillé'])
            ->and($noms(['etat' => 'inactif']))->toBe(['Agent Désactivé'])
            ->and($noms(['search' => ['value' => 'yao.inac']]))->toBe(['Agent Désactivé']);
    });

    it('rejects unknown filters', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->getJson(route('gestion.utilisateurs.donnees', ['role' => 'partenaire', 'etat' => 'supprime']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'etat']);
    });

    it('is reserved to accounts that manage users', function (Role $role) {
        connecter(utilisateurAvecRole($role))->get(route('gestion.utilisateurs.index'))->assertForbidden();
    })->with([Role::Agent, Role::Partenaire]);

    it('shows the settings menu entry to the admin only', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.tableau-de-bord'))->assertSee(route('gestion.utilisateurs.index'), false);
        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))->assertDontSee(route('gestion.utilisateurs.index'), false);
    });
});

describe('création', function () {
    it('lets an admin create an agent and shows its pin once', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        $reponse = connecter($admin)->post(route('gestion.utilisateurs.store'), [
            'nom' => 'Yao  Kouamé', 'nom_utilisateur' => 'Yao.Kouame', 'telephone' => '0707123456', 'email' => 'Yao@Exemple.ci', 'role' => 'agent',
        ]);

        $compte = User::where('nom_utilisateur', 'yao.kouame')->sole();
        $reponse->assertRedirect(route('gestion.utilisateurs.show', $compte));
        $pin = $reponse->getSession()->get('pin_genere')['pin'];

        expect($compte)
            ->nom->toBe('Yao Kouamé')
            ->email->toBe('yao@exemple.ci')
            ->telephone->toBe('+2250707123456')
            ->cree_par_id->toBe($admin->id)
            ->and($compte->hasRole(Role::Agent))->toBeTrue()
            ->and(Hash::check($pin, $compte->password))->toBeTrue();
    });

    it('never lets an admin create another admin', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.utilisateurs.store'), ['nom_utilisateur' => 'faux.admin', 'role' => 'admin'])
            ->assertSessionHasErrors(['role' => 'Vous ne pouvez pas attribuer ce rôle.']);

        expect(User::where('nom_utilisateur', 'faux.admin')->exists())->toBeFalse();
    });

    it('lets the superadmin create an admin', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.utilisateurs.store'), ['nom_utilisateur' => 'nouvel.admin', 'role' => 'admin'])
            ->assertSessionHasNoErrors();

        expect(User::where('nom_utilisateur', 'nouvel.admin')->sole()->hasRole(Role::Admin))->toBeTrue();
    });

    it('never creates a superadmin with a pin, even for the superadmin', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        connecter($superadmin)->post(route('gestion.utilisateurs.store'), ['nom_utilisateur' => 'autre.super', 'role' => 'superadmin'])
            ->assertSessionHasErrors('role');

        expect(fn () => app(CreerCompteAction::class)(['nom' => 'X', 'nom_utilisateur' => 'autre.super'], Role::Superadmin, $superadmin))
            ->toThrow(OperationCompteException::class);
    });

    it('rejects a username or e-mail already used, deleted accounts included', function () {
        $pris = User::factory()->create(['nom_utilisateur' => 'deja.pris', 'email' => 'pris@exemple.ci']);
        $pris->delete();

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.utilisateurs.store'), ['nom_utilisateur' => 'deja.pris', 'email' => 'PRIS@exemple.ci', 'role' => 'agent'])
            ->assertSessionHasErrors(['nom_utilisateur', 'email']);
    });
});

describe('fiche et modification', function () {
    it('shows who created and last modified the account, and its activity', function () {
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);
        $agent = utilisateurAvecRole(Role::Agent, ['cree_par_id' => $admin->id]);

        connecter($admin)->put(route('gestion.comptes.update', $agent), ['nom' => 'Nouveau Nom', 'nom_utilisateur' => $agent->nom_utilisateur, 'role' => 'agent'])
            ->assertRedirect(route('gestion.utilisateurs.show', $agent));

        connecter($admin)->get(route('gestion.utilisateurs.show', $agent))
            ->assertOk()
            ->assertSeeInOrder(['Nouveau Nom', 'Créé le', 'Awa Koné · Administrateur', 'Modifié le', 'Awa Koné · Administrateur', 'Cartes activées']);
    });

    it('does not show partner users on the back-office user page', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->get(route('gestion.utilisateurs.show', utilisateurAvecRole(Role::Partenaire)))
            ->assertNotFound();
    });

    it('changes the username: the user logs in with the new one, same pin', function () {
        $agent = utilisateurAvecRole(Role::Agent, ['nom_utilisateur' => 'ancien.nom']);
        $agent->forceFill(['password' => '24680'])->save();
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->put(route('gestion.comptes.update', $agent), ['nom_utilisateur' => 'Nouveau.Nom', 'role' => 'agent'])
            ->assertSessionHas('succes');
        auth()->logout();

        $this->post(route('login.store'), ['nom_utilisateur' => 'ancien.nom', 'password' => '24680'])->assertSessionHasErrors();
        $this->post(route('login.store'), ['nom_utilisateur' => 'nouveau.nom', 'password' => '24680'])->assertRedirect(route('accueil-espace'));

        expect($agent->fresh()->modifie_par_id)->toBe($admin->id)
            ->and(JournalAudit::where('action', 'utilisateur.modifie')->where('entite_id', $agent->id)->latest('id')->first()->donnees['apres'])
            ->toMatchArray(['nom_utilisateur' => 'nouveau.nom']);
    });

    it('falls back on the username when the name is emptied', function () {
        $agent = utilisateurAvecRole(Role::Agent, ['nom_utilisateur' => 'yao.k']);

        connecter(utilisateurAvecRole(Role::Admin))->put(route('gestion.comptes.update', $agent), ['nom' => '', 'nom_utilisateur' => 'yao.k', 'role' => 'agent']);

        expect($agent->fresh()->nom)->toBe('yao.k');
    });

    it('lets the superadmin promote an agent, and audits the role change', function () {
        $agent = utilisateurAvecRole(Role::Agent);

        connecter(utilisateurAvecRole(Role::Superadmin))
            ->put(route('gestion.comptes.update', $agent), ['nom_utilisateur' => $agent->nom_utilisateur, 'role' => 'admin'])
            ->assertSessionHas('succes');

        expect($agent->fresh()->hasRole(Role::Admin))->toBeTrue()
            ->and($agent->fresh()->hasRole(Role::Agent))->toBeFalse()
            ->and(JournalAudit::where('action', 'role.attribue')->where('entite_id', $agent->id)->exists())->toBeTrue();
    });

    it('never lets an admin promote an agent to admin', function () {
        $agent = utilisateurAvecRole(Role::Agent);

        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.comptes.update', $agent), ['nom_utilisateur' => $agent->nom_utilisateur, 'role' => 'admin'])
            ->assertSessionHasErrors('role');

        expect($agent->fresh()->hasRole(Role::Agent))->toBeTrue();
    });

    it('never lets anyone edit their own account, an equal admin or the superadmin', function (Role $acteur, ?Role $cible) {
        $auteur = utilisateurAvecRole($acteur);
        $compte = $cible === null ? $auteur : utilisateurAvecRole($cible);

        connecter($auteur)->get(route('gestion.comptes.edit', $compte))->assertForbidden();
        connecter($auteur)->put(route('gestion.comptes.update', $compte), ['nom_utilisateur' => 'pirate', 'role' => 'agent'])->assertForbidden();

        expect($compte->fresh()->nom_utilisateur)->not->toBe('pirate');
    })->with([
        'soi-même' => [Role::Admin, null],
        'autre admin' => [Role::Admin, Role::Admin],
        'superadmin' => [Role::Admin, Role::Superadmin],
        'superadmin par superadmin' => [Role::Superadmin, Role::Superadmin],
    ]);

    it('edits a partner user from the partner page, without ever changing its role', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);

        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.comptes.update', $operateur), ['nom' => 'Caisse 2', 'nom_utilisateur' => 'caisse.deux', 'role' => 'admin'])
            ->assertRedirect(route('gestion.partenaires.show', $operateur->partenaire_id));

        expect($operateur->fresh())
            ->nom->toBe('Caisse 2')
            ->nom_utilisateur->toBe('caisse.deux')
            ->and($operateur->fresh()->hasRole(Role::Partenaire))->toBeTrue()
            ->and($operateur->fresh()->hasRole(Role::Admin))->toBeFalse();
    });
});

describe('anti-élévation de privilèges', function () {
    it('refuses to manage an agent that holds a permission the admin lacks', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::PurgerJournalAudit->value);

        expect(GardeDroits::peutGererCompte($admin, $agent))->toBeFalse();

        confirme($admin)->post(route('gestion.comptes.pin', $agent))->assertForbidden();
        connecter($admin)->put(route('gestion.comptes.update', $agent), ['nom_utilisateur' => $agent->nom_utilisateur, 'role' => 'agent'])
            ->assertForbidden();
    });

    it('refuses to create agents when the agent role holds a permission the admin lacks', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        ModeleRole::findByName(Role::Agent->value)->givePermissionTo(Permission::PurgerJournalAudit->value);

        expect(GardeDroits::rolesGestionAttribuables($admin))->toBe([]);
        connecter($admin)->get(route('gestion.utilisateurs.create'))->assertForbidden();
    });
});

describe('réinitialisation du PIN', function () {
    it('requires the dedicated permission', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        ModeleRole::findByName(Role::Admin->value)->revokePermissionTo(Permission::ReinitialiserPin->value);
        $agent = utilisateurAvecRole(Role::Agent);

        connecter($admin)->get(route('gestion.utilisateurs.show', $agent))
            ->assertOk()
            ->assertDontSee('action="'.route('gestion.comptes.pin', $agent).'"', false);
        confirme($admin)->post(route('gestion.comptes.pin', $agent))->assertForbidden();

        expect(fn () => app(GererCompteAction::class)->reinitialiserPin($agent, $admin))
            ->toThrow(OperationCompteException::class, 'Vous n\'avez pas le droit de réinitialiser un PIN.');
    });

    it('lets the superadmin and an admin with the permission reset a pin', function (Role $role) {
        $agent = utilisateurAvecRole(Role::Agent);

        confirme(utilisateurAvecRole($role))->post(route('gestion.comptes.pin', $agent))->assertSessionHas('pin_genere');
    })->with([Role::Superadmin, Role::Admin]);
});

it('lets an agent granted the permission create partner users', function () {
    $agent = utilisateurAvecRole(Role::Agent);
    $agent->givePermissionTo(Permission::GererOperateursPartenaires->value);
    $partenaire = Partenaire::factory()->create();

    connecter($agent)->post(route('gestion.partenaires.operateurs.store', $partenaire), ['nom_utilisateur' => 'caisse.agent'])
        ->assertSessionHas('pin_genere');

    expect(User::where('nom_utilisateur', 'caisse.agent')->sole())
        ->partenaire_id->toBe($partenaire->id)
        ->cree_par_id->toBe($agent->id);
});
