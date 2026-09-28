<?php

use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Enums\StatutDemandeOtp;
use App\Enums\TypeSms;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use App\Models\Titulaire;
use App\Models\User;
use Tests\TestCase;

/**
 * Session avec un PIN confirmé il y a $secondes secondes.
 */
function avecPinConfirme(User $user, int $secondes = 10): TestCase
{
    return connecter($user)->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->subSeconds($secondes)->getTimestamp(),
    ]);
}

describe('statut de la carte', function () {
    it('lets an admin suspend a card with a reason', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.cartes.statut', $carte), ['statut' => 'suspendue', 'motif' => 'Contrôle'])
            ->assertRedirect(route('gestion.cartes.show', $carte))
            ->assertSessionHas('succes');

        expect($carte->fresh()->statut)->toBe(StatutCarte::Suspendue);
    });

    it('forbids an agent without the permission', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('gestion.cartes.statut', $carte), ['statut' => 'revoquee', 'motif' => 'Perdue'])
            ->assertForbidden();

        expect($carte->fresh()->statut)->toBe(StatutCarte::Active);
    });

    it('requires a reason and refuses a manual expiry', function (array $donnees, string $champ) {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.cartes.statut', Carte::factory()->create()), $donnees)
            ->assertSessionHasErrors($champ);
    })->with([
        'motif absent' => [['statut' => 'suspendue', 'motif' => ''], 'motif'],
        'expiration manuelle' => [['statut' => 'expiree', 'motif' => 'Test'], 'statut'],
        'statut inconnu' => [['statut' => 'detruite', 'motif' => 'Test'], 'statut'],
    ]);

    it('still refuses an impossible transition for the superadmin', function () {
        $carte = Carte::factory()->revoquee()->create();

        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.cartes.statut', $carte), ['statut' => 'active', 'motif' => 'Forcer'])
            ->assertSessionHas('erreur');

        expect($carte->fresh()->statut)->toBe(StatutCarte::Revoquee);
    });

    it('shows only the possible actions on the card page', function () {
        $carte = Carte::factory()->suspendue()->create();

        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.show', $carte))
            ->assertSee('Réactiver')
            ->assertSee('Révoquer (perte, vol…)')
            ->assertDontSee('>Suspendre<', false);
    });

    it('makes a suspended card unusable at a partner', function () {
        $carte = Carte::factory()->create(['numero_carte' => '4567890']);
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.cartes.statut', $carte), ['statut' => 'suspendue', 'motif' => 'Contrôle']);

        connecter(utilisateurAvecRole(Role::Partenaire))->followingRedirects()
            ->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '4567890'])
            ->assertSee('Carte non valide');
    });
});

describe('identité du titulaire', function () {
    it('lets an admin update the names, normalised and audited', function () {
        $carte = Carte::factory()->for(Titulaire::factory()->create(['nom' => 'ANCIEN']))->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.cartes.titulaire.update', $carte), ['nom' => 'kouassi', 'prenom' => 'aya marie'])
            ->assertRedirect(route('gestion.cartes.show', $carte));

        expect($carte->titulaire->fresh())
            ->nom->toBe('KOUASSI')
            ->prenom->toBe('Aya Marie')
            ->and(JournalAudit::where('action', 'titulaire.modifie')->sole()->donnees['avant']['nom'])->toBe('ANCIEN');
    });

    it('forbids an agent from editing the holder', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.cartes.titulaire.edit', $carte))->assertForbidden();
        connecter(utilisateurAvecRole(Role::Agent))
            ->put(route('gestion.cartes.titulaire.update', $carte), ['nom' => 'X', 'prenom' => 'Y'])
            ->assertForbidden();
    });

    it('validates the names', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.cartes.titulaire.update', Carte::factory()->create()), ['nom' => 'K2', 'prenom' => ''])
            ->assertSessionHasErrors(['nom', 'prenom']);
    });
});

describe('téléphone du titulaire', function () {
    it('asks for the pin before showing the phone form', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->get(route('gestion.cartes.titulaire.telephone.edit', Carte::factory()->create()))
            ->assertRedirect(route('password.confirm'));
    });

    it('asks again when the pin was confirmed more than five minutes ago', function () {
        avecPinConfirme(utilisateurAvecRole(Role::Admin), 301)
            ->get(route('gestion.cartes.titulaire.telephone.edit', Carte::factory()->create()))
            ->assertRedirect(route('password.confirm'));
    });

    it('changes the phone, warns the old number and cancels pending codes', function () {
        $carte = Carte::factory()->for(Titulaire::factory()->create(['telephone' => '+2250707123456']))->create();
        $demande = DemandeOtp::factory()->create(['carte_id' => $carte->id]);

        avecPinConfirme(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.cartes.titulaire.telephone.update', $carte), ['telephone' => '05 05 12 34 56'])
            ->assertRedirect(route('gestion.cartes.show', $carte));

        $sms = MessageSms::sole();

        expect($carte->titulaire->fresh()->telephone)->toBe('+2250505123456')
            ->and($sms->telephone)->toBe('+2250707123456')
            ->and($sms->type)->toBe(TypeSms::Information)
            ->and($sms->contenu)->toContain('a été modifié')
            ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Expiree)
            ->and(JournalAudit::where('action', 'titulaire.modifie')->sole()->donnees['apres']['telephone'])->toBe('+2250505123456');
    });

    it('refuses a number already used by another holder', function () {
        Titulaire::factory()->create(['telephone' => '+2250505123456']);
        $carte = Carte::factory()->create();

        avecPinConfirme(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.cartes.titulaire.telephone.update', $carte), ['telephone' => '0505123456'])
            ->assertSessionHasErrors(['telephone' => 'Ce numéro est déjà associé à un autre titulaire.']);

        expect(MessageSms::count())->toBe(0);
    });

    it('forbids the phone change without the dedicated permission, even with the pin', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        Spatie\Permission\Models\Role::findByName('admin')->revokePermissionTo('modifier-telephone-titulaire');

        avecPinConfirme($admin->fresh())
            ->put(route('gestion.cartes.titulaire.telephone.update', Carte::factory()->create()), ['telephone' => '0505123456'])
            ->assertForbidden();
    });
});

describe('historique sur la fiche carte', function () {
    it('shows the ten latest operations and links to the full history of this card', function () {
        $carte = Carte::factory()->create(['numero_carte' => '4567890']);
        $this->actingAs(utilisateurAvecRole(Role::Admin));

        foreach (range(1, 6) as $i) {
            $carte->update(['statut' => StatutCarte::Suspendue, 'motif_statut' => "Suspension : contrôle {$i}"]);
            $carte->update(['statut' => StatutCarte::Active, 'motif_statut' => "Réactivation : ok {$i}"]);
        }

        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.show', $carte))
            ->assertSee('(10 dernières sur 13)')
            ->assertSee(route('gestion.cartes.rapport', ['carte' => '4567890']), false)
            ->assertDontSee('Suspension : contrôle 1<', false);
    });

    it('hides the full history link without the report permission', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        Spatie\Permission\Models\Role::findByName('agent')->revokePermissionTo('voir-rapport-cartes');

        connecter($agent->fresh())->get(route('gestion.cartes.show', Carte::factory()->create()))
            ->assertOk()
            ->assertDontSee("Voir tout l'historique", false);
    });
});
