<?php

use App\Actions\Comptes\GererCompteAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutDemandeOtp;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('création d\'un opérateur', function () {
    it('creates an operator attached to the partner and shows its pin once', function () {
        $partenaire = Partenaire::factory()->create();

        $reponse = connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', $partenaire), [
                'nom' => 'Aya Caisse', 'nom_utilisateur' => 'Pharma.Caisse1', 'telephone' => '0707123456',
            ])
            ->assertRedirect(route('gestion.partenaires.show', $partenaire));

        $operateur = User::where('nom_utilisateur', 'pharma.caisse1')->sole();
        $pin = $reponse->getSession()->get('pin_genere')['pin'];

        expect($operateur)
            ->partenaire_id->toBe($partenaire->id)
            ->telephone->toBe('+2250707123456')
            ->and($operateur->hasRole(Role::Partenaire))->toBeTrue()
            ->and($pin)->toMatch('/^\d{5}$/')
            ->and(Hash::check($pin, $operateur->password))->toBeTrue()
            ->and(JournalAudit::where('action', 'utilisateur.cree')->get()->toJson())->not->toContain($pin);
    });

    it('lets the new operator log in with the generated pin', function () {
        $partenaire = Partenaire::factory()->create();
        $reponse = connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', $partenaire), ['nom' => 'Caisse', 'nom_utilisateur' => 'caisse.test']);
        $pin = $reponse->getSession()->get('pin_genere')['pin'];

        auth()->logout();

        $this->post(route('login.store'), ['nom_utilisateur' => 'caisse.test', 'password' => $pin])
            ->assertRedirect(route('accueil-espace'));
    });

    it('validates the username', function (string $nomUtilisateur) {
        User::factory()->create(['nom_utilisateur' => 'deja.pris']);

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', Partenaire::factory()->create()), ['nom' => 'X Y', 'nom_utilisateur' => $nomUtilisateur])
            ->assertSessionHasErrors('nom_utilisateur');
    })->with(['deja.pris', 'avec espace', 'ab', 'accentué']);

    it('forbids an agent from creating operators', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('gestion.partenaires.operateurs.store', Partenaire::factory()->create()), ['nom' => 'X', 'nom_utilisateur' => 'xxx'])
            ->assertForbidden();
    });
});

describe('gestion d\'un compte', function () {
    it('asks for the pin before resetting another pin', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.comptes.pin', utilisateurAvecRole(Role::Partenaire)))
            ->assertRedirect(route('password.confirm'));
    });

    it('resets the pin, unlocks the account and shows the new pin once', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $operateur->forceFill(['verrouille_le' => now(), 'tentatives_echouees' => 10])->save();

        $reponse = avecPinRecent(utilisateurAvecRole(Role::Admin))->post(route('gestion.comptes.pin', $operateur));
        $pin = $reponse->getSession()->get('pin_genere')['pin'];

        expect(Hash::check($pin, $operateur->fresh()->password))->toBeTrue()
            ->and($operateur->fresh()->estVerrouille())->toBeFalse()
            ->and($operateur->fresh()->tentatives_echouees)->toBe(0);
    });

    it('locks and unlocks an account', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->post(route('gestion.comptes.verrouillage', $operateur), ['verrouiller' => 1]);
        expect($operateur->fresh()->estVerrouille())->toBeTrue();

        connecter($admin)->post(route('gestion.comptes.verrouillage', $operateur), ['verrouiller' => 0]);
        expect($operateur->fresh()->estVerrouille())->toBeFalse();
    });

    it('logs a deactivated operator out at its next request', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);

        connecter(utilisateurAvecRole(Role::Admin))->post(route('gestion.comptes.statut', $operateur), ['statut' => 'inactif']);

        expect($operateur->fresh()->statut)->toBe(StatutUtilisateur::Inactif);
        connecter($operateur->fresh())->get(route('partenaire.tableau-de-bord'))->assertRedirect(route('login'));
    });

    it('never lets an admin manage another admin or a superadmin', function (Role $role) {
        $cible = utilisateurAvecRole($role);

        connecter(utilisateurAvecRole(Role::Admin))->post(route('gestion.comptes.verrouillage', $cible), ['verrouiller' => 1])
            ->assertForbidden();

        expect($cible->fresh()->estVerrouille())->toBeFalse();
    })->with([Role::Admin, Role::Superadmin]);

    it('never lets anyone manage their own account', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->post(route('gestion.comptes.statut', $admin), ['statut' => 'inactif'])->assertForbidden();
    });

    it('forbids an agent from managing operators', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('gestion.comptes.verrouillage', utilisateurAvecRole(Role::Partenaire)), ['verrouiller' => 1])
            ->assertForbidden();
    });

    it('logs lock and pin reset without the pin', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        avecPinRecent(utilisateurAvecRole(Role::Admin))->post(route('gestion.comptes.pin', $operateur));

        expect(JournalAudit::where('action', 'utilisateur.pin_reinitialise')->where('entite_id', $operateur->id)->exists())->toBeTrue();
    });
});

describe('nom de l\'utilisateur du partenaire', function () {
    it('makes the full name optional and falls back on the username', function () {
        $partenaire = Partenaire::factory()->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', $partenaire), ['nom' => '   ', 'nom_utilisateur' => 'Caisse.Deux'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pin_genere');

        expect(User::where('nom_utilisateur', 'caisse.deux')->sole())
            ->nom->toBe('caisse.deux')
            ->partenaire_id->toBe($partenaire->id);
    });

    it('still rejects a one-letter name', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', Partenaire::factory()->create()), ['nom' => 'A', 'nom_utilisateur' => 'caisse.trois'])
            ->assertSessionHasErrors('nom');
    });
});

describe('suppression d\'un compte', function () {
    it('asks for the password before deleting', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);

        connecter(utilisateurAvecRole(Role::Admin))
            ->delete(route('gestion.comptes.supprimer', $operateur))
            ->assertRedirect(route('password.confirm'));

        expect($operateur->fresh()->trashed())->toBeFalse();
    });

    it('archives the account, expires its pending codes and audits the deletion', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $admin = utilisateurAvecRole(Role::Admin);
        $demande = DemandeOtp::factory()->create(['partenaire_id' => $operateur->partenaire_id, 'demandee_par_id' => $operateur->id]);

        avecPinRecent($admin)->delete(route('gestion.comptes.supprimer', $operateur))->assertSessionHas('succes');

        expect(User::find($operateur->id))->toBeNull()
            ->and(User::withTrashed()->find($operateur->id)->trashed())->toBeTrue()
            ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Expiree)
            ->and(JournalAudit::where('action', 'utilisateur.supprime')->where('entite_id', $operateur->id)->sole()->acteur_id)->toBe($admin->id);

        connecter($admin)->get(route('gestion.partenaires.show', $operateur->partenaire_id))
            ->assertOk()
            ->assertDontSee('@'.$operateur->nom_utilisateur);
    });

    it('prevents a deleted account from logging in and keeps its username reserved', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire, ['nom_utilisateur' => 'caisse.archivee']);
        $operateur->forceFill(['password' => '24680'])->save();

        avecPinRecent(utilisateurAvecRole(Role::Admin))->delete(route('gestion.comptes.supprimer', $operateur));
        auth()->logout();

        $this->post(route('login.store'), ['nom_utilisateur' => 'caisse.archivee', 'password' => '24680'])
            ->assertSessionHasErrors();
        $this->assertGuest();

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.operateurs.store', Partenaire::factory()->create()), ['nom_utilisateur' => 'caisse.archivee'])
            ->assertSessionHasErrors('nom_utilisateur');
    });

    it('keeps the author name of past transactions', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire, ['nom' => 'Aya Caisse']);
        $transaction = Transaction::factory()->create(['valide_par_id' => $operateur->id]);

        avecPinRecent(utilisateurAvecRole(Role::Admin))->delete(route('gestion.comptes.supprimer', $operateur));

        expect($transaction->fresh()->validePar->nom)->toBe('Aya Caisse');
    });

    it('shows the delete button only to accounts allowed to delete', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $url = 'action="'.route('gestion.comptes.supprimer', $operateur).'"';
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::GererOperateursPartenaires->value);

        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.partenaires.show', $operateur->partenaire_id))->assertSee($url, false);
        connecter($agent)->get(route('gestion.partenaires.show', $operateur->partenaire_id))->assertDontSee($url, false);
    });

    it('requires the dedicated permission, even for someone who manages the account', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::GererOperateursPartenaires->value);

        avecPinRecent($agent)->delete(route('gestion.comptes.supprimer', $operateur))->assertForbidden();

        $agent->givePermissionTo(Permission::SupprimerComptes->value);
        avecPinRecent($agent)->delete(route('gestion.comptes.supprimer', $operateur))->assertSessionHas('succes');

        expect(User::find($operateur->id))->toBeNull();
    });

    it('never lets an admin delete their own account, another admin or a superadmin', function (?Role $role) {
        $admin = utilisateurAvecRole(Role::Admin);
        $cible = $role === null ? $admin : utilisateurAvecRole($role);

        avecPinRecent($admin)->delete(route('gestion.comptes.supprimer', $cible))->assertForbidden();

        expect(User::find($cible->id))->not->toBeNull();
    })->with(['soi-même' => [null], 'admin' => [Role::Admin], 'superadmin' => [Role::Superadmin]]);

    it('refuses the deletion in the action as well', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::GererOperateursPartenaires->value);

        expect(fn () => app(GererCompteAction::class)->supprimer(utilisateurAvecRole(Role::Partenaire), $agent))
            ->toThrow(OperationCompteException::class, 'Vous n\'avez pas le droit de supprimer ce compte.');
    });
});
