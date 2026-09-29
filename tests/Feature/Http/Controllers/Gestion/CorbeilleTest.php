<?php

use App\Actions\Comptes\GererCompteAction;
use App\Actions\Corbeille\RestaurerAction;
use App\Actions\Gestion\EnregistrerPartenaireAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\RestaurationException;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\Droits\SynchroniserPermissions;
use Spatie\Permission\Models\Role as ModeleRole;
use Tests\TestCase;

function superadminConfirme(): TestCase
{
    return connecter(utilisateurAvecRole(Role::Superadmin))->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

/**
 * Partenaire supprimé par un admin, avec un utilisateur.
 *
 * @return array{partenaire: Partenaire, operateur: User, admin: User}
 */
function partenaireSupprime(string $nom = 'Pharmacie Archivée'): array
{
    $operateur = utilisateurAvecRole(Role::Partenaire, ['nom_utilisateur' => 'caisse.archivee']);
    $operateur->partenaire->update(['nom' => $nom]);
    $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);

    app(EnregistrerPartenaireAction::class)->supprimer($operateur->partenaire, $admin);

    return ['partenaire' => $operateur->partenaire, 'operateur' => $operateur, 'admin' => $admin];
}

describe('accès', function () {
    it('is reserved to the superadmin', function (Role $role) {
        connecter(utilisateurAvecRole($role))->get(route('gestion.corbeille.index'))->assertForbidden();
    })->with([Role::Admin, Role::Agent]);

    it('stays closed to an admin even if the permission were granted directly', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $admin->givePermissionTo(Permission::RestaurerElements->value);

        connecter($admin)->get(route('gestion.corbeille.index'))->assertForbidden();
    });

    it('shows the menu entry to the superadmin only', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.tableau-de-bord'))
            ->assertSee(route('gestion.corbeille.index'), false);
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.tableau-de-bord'))
            ->assertDontSee(route('gestion.corbeille.index'), false);
    });

    it('never lets a role receive a superadmin-only permission', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        expect(GardeDroits::peutModifierPermissionDuRole($superadmin, Role::Admin, Permission::RestaurerElements))->toBeFalse()
            ->and(GardeDroits::peutModifierPermissionDuRole($superadmin, Role::Admin, Permission::VoirCommandesProduction))->toBeFalse();

        ModeleRole::findByName(Role::Admin->value)->givePermissionTo(Permission::RestaurerElements->value);
        app(SynchroniserPermissions::class)();

        expect(ModeleRole::findByName(Role::Admin->value)->hasPermissionTo(Permission::RestaurerElements->value))->toBeFalse();
    });
});

describe('liste', function () {
    it('lists deleted partners and accounts with who deleted them', function () {
        partenaireSupprime();

        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.corbeille.index'))
            ->assertOk()
            ->assertSeeInOrder(['Partenaires supprimés', 'Pharmacie Archivée', 'Awa Koné · Administrateur'])
            ->assertSeeInOrder(['Comptes supprimés', '@caisse.archivee', 'Pharmacie Archivée', 'Restaurez d&#039;abord le partenaire'], false)
            ->assertSee('avec ses 1 utilisateur(s)');
    });

    it('records the author of a deletion and clears it on restore', function () {
        ['partenaire' => $partenaire, 'operateur' => $operateur, 'admin' => $admin] = partenaireSupprime();

        expect(Partenaire::withTrashed()->find($partenaire->id)->supprime_par_id)->toBe($admin->id)
            ->and(User::withTrashed()->find($operateur->id)->supprime_par_id)->toBe($admin->id);

        superadminConfirme()->post(route('gestion.corbeille.partenaires.restaurer', $partenaire), ['avec_utilisateurs' => 1]);

        expect($partenaire->fresh()->supprime_par_id)->toBeNull()
            ->and($operateur->fresh()->supprime_par_id)->toBeNull();
    });
});

describe('restauration', function () {
    it('asks for the password before restoring', function () {
        ['partenaire' => $partenaire] = partenaireSupprime();

        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.corbeille.partenaires.restaurer', $partenaire))
            ->assertRedirect(route('password.confirm'));

        expect(Partenaire::find($partenaire->id))->toBeNull();
    });

    it('restores a partner with its users, and audits it', function () {
        ['partenaire' => $partenaire, 'operateur' => $operateur] = partenaireSupprime();

        superadminConfirme()->post(route('gestion.corbeille.partenaires.restaurer', $partenaire), ['avec_utilisateurs' => 1])
            ->assertSessionHas('succes', 'Le partenaire Pharmacie Archivée est restauré avec 1 utilisateur(s).');

        expect(Partenaire::find($partenaire->id))->not->toBeNull()
            ->and(User::find($operateur->id))->not->toBeNull()
            ->and(JournalAudit::where('action', 'partenaire.restaure')->where('entite_id', $partenaire->id)->exists())->toBeTrue()
            ->and(JournalAudit::where('action', 'utilisateur.restaure')->where('entite_id', $operateur->id)->exists())->toBeTrue()
            ->and(JournalAudit::where('action', 'utilisateur.modifie')->where('entite_id', $operateur->id)->exists())->toBeFalse();
    });

    it('restores a partner alone when asked, its users staying deleted', function () {
        ['partenaire' => $partenaire, 'operateur' => $operateur] = partenaireSupprime();

        superadminConfirme()->post(route('gestion.corbeille.partenaires.restaurer', $partenaire))
            ->assertSessionHas('succes', 'Le partenaire Pharmacie Archivée est restauré.');

        expect(Partenaire::find($partenaire->id))->not->toBeNull()
            ->and(User::find($operateur->id))->toBeNull();

        superadminConfirme()->post(route('gestion.corbeille.comptes.restaurer', $operateur))->assertSessionHas('succes');
        expect(User::find($operateur->id))->not->toBeNull();
    });

    it('lets a restored operator log in again with the same pin', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire, ['nom_utilisateur' => 'caisse.revenue']);
        $operateur->forceFill(['password' => '24680'])->save();
        app(GererCompteAction::class)->archiver($operateur, null);

        superadminConfirme()->post(route('gestion.corbeille.comptes.restaurer', $operateur));
        auth()->logout();

        $this->post(route('login.store'), ['nom_utilisateur' => 'caisse.revenue', 'password' => '24680'])
            ->assertRedirect(route('accueil-espace'));
    });

    it('refuses to restore an account whose partner is still deleted', function () {
        ['operateur' => $operateur] = partenaireSupprime();

        superadminConfirme()->post(route('gestion.corbeille.comptes.restaurer', $operateur))
            ->assertSessionHas('erreur', 'Son partenaire est supprimé : restaurez d\'abord le partenaire.');

        expect(User::find($operateur->id))->toBeNull();
    });

    it('refuses to restore an element that is not deleted', function () {
        $partenaire = Partenaire::factory()->create();

        superadminConfirme()->post(route('gestion.corbeille.partenaires.restaurer', $partenaire))
            ->assertSessionHas('erreur', 'Ce partenaire n\'est pas supprimé.');
    });

    it('forbids the restore routes to an admin', function () {
        ['partenaire' => $partenaire, 'operateur' => $operateur] = partenaireSupprime();
        $admin = utilisateurAvecRole(Role::Admin);
        $session = ['connecte_le' => now()->getTimestamp(), 'auth.password_confirmed_at' => now()->getTimestamp()];

        connecter($admin)->withSession($session)->post(route('gestion.corbeille.partenaires.restaurer', $partenaire))->assertForbidden();
        connecter($admin)->withSession($session)->post(route('gestion.corbeille.comptes.restaurer', $operateur))->assertForbidden();

        expect(Partenaire::find($partenaire->id))->toBeNull();
    });

    it('refuses the restore in the action for anyone but the superadmin', function () {
        ['partenaire' => $partenaire] = partenaireSupprime();

        expect(fn () => app(RestaurerAction::class)->partenaire($partenaire, utilisateurAvecRole(Role::Admin), true))
            ->toThrow(RestaurationException::class, 'La restauration est réservée au superadmin.');
    });
});

describe('mise en production', function () {
    it('shows the production commands to the superadmin', function () {
        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.mise-en-production'))
            ->assertOk()
            ->assertSee('Mise en production')
            ->assertSee('php artisan permissions:synchroniser')
            ->assertSee('composer install --no-dev --optimize-autoloader')
            ->assertSee('<pre>', false);
    });

    it('is reserved to the superadmin', function (Role $role) {
        connecter(utilisateurAvecRole($role))->get(route('gestion.mise-en-production'))->assertForbidden();
    })->with([Role::Admin, Role::Agent]);
});
