<?php

use App\Enums\CanalAlerte;
use App\Enums\PalierAlerte;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\AlerteExpiration;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use App\Models\OperationCarte;
use App\Models\SuppressionCarte;
use App\Models\Titulaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StatistiquesTableauDeBord;
use Illuminate\Database\QueryException;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Suppression définitive d'une carte et de tout son historique (cartes de test)
|--------------------------------------------------------------------------
*/

/**
 * Carte « de test » complète : une transaction (et son code), un code non
 * utilisé, une alerte d'expiration avec son SMS, un SMS de code déjà envoyé.
 */
function carteAvecHistorique(): Carte
{
    $carte = Carte::factory()->create();

    Transaction::factory()->create(['demande_otp_id' => DemandeOtp::factory()->utilisee()->create(['carte_id' => $carte->id])->id]);
    DemandeOtp::factory()->create(['carte_id' => $carte->id]);

    $smsAlerte = smsPour('+2250500000000', TypeSms::AlerteExpiration);
    AlerteExpiration::create([
        'carte_id' => $carte->id, 'palier' => PalierAlerte::UnMois, 'canal' => CanalAlerte::Sms,
        'message_sms_id' => $smsAlerte->id, 'envoyee_le' => now(),
    ]);
    smsPour($carte->titulaire->telephone);

    return $carte;
}

function smsPour(string $telephone, TypeSms $type = TypeSms::Otp, StatutLivraison $statut = StatutLivraison::Envoyee): MessageSms
{
    return MessageSms::create(['telephone' => $telephone, 'type' => $type, 'contenu' => 'Message', 'statut' => $statut, 'fournisseur' => 'simulation']);
}

function supprimerCarte(User $user, Carte $carte, array $saisie = []): TestResponse
{
    return avecPinRecent($user)->delete(route('gestion.cartes.destroy', $carte), [
        'motif' => 'Carte de test avant ouverture',
        'numero_confirmation' => $carte->numero_carte,
        ...$saisie,
    ]);
}

describe('suppression', function () {
    it('erases the card with its transactions, codes, alerts, operations, holder and sms', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);
        $carte = carteAvecHistorique();
        $titulaire = $carte->titulaire;

        supprimerCarte($superadmin, $carte)
            ->assertRedirect(route('gestion.cartes.index'))
            ->assertSessionHas('succes');

        expect(Carte::withTrashed()->find($carte->id))->toBeNull()
            ->and(Transaction::where('carte_id', $carte->id)->count())->toBe(0)
            ->and(DemandeOtp::where('carte_id', $carte->id)->count())->toBe(0)
            ->and(AlerteExpiration::where('carte_id', $carte->id)->count())->toBe(0)
            ->and(OperationCarte::where('carte_id', $carte->id)->count())->toBe(0)
            ->and(Titulaire::withTrashed()->find($titulaire->id))->toBeNull()
            ->and(MessageSms::count())->toBe(0);
    });

    it('records the deletion in the permanent register and in the audit journal, without personal data', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);
        $carte = carteAvecHistorique();
        $titulaire = $carte->titulaire;

        supprimerCarte($superadmin, $carte, ['motif' => '  Carte   de test  ']);

        $registre = SuppressionCarte::sole();
        expect($registre->only(['numero_carte', 'supprime_par_id', 'motif', 'transactions_supprimees', 'codes_supprimes', 'alertes_supprimees', 'operations_supprimees', 'sms_supprimes', 'titulaire_supprime']))
            ->toBe([
                'numero_carte' => $carte->numero_carte, 'supprime_par_id' => $superadmin->id, 'motif' => 'Carte de test',
                'transactions_supprimees' => 1, 'codes_supprimes' => 2, 'alertes_supprimees' => 1, 'operations_supprimees' => 1,
                'sms_supprimes' => 2, 'titulaire_supprime' => true,
            ])
            ->and(json_encode($registre->getAttributes()))->not->toContain($titulaire->nom)->not->toContain($titulaire->telephone);

        $entree = JournalAudit::where('action', 'carte.supprimee_definitivement')->sole();
        expect($entree->acteur_id)->toBe($superadmin->id)
            ->and($entree->donnees)->toMatchArray(['numero_carte' => $carte->numeroFormate(), 'motif' => 'Carte de test', 'transactions_supprimees' => 1])
            ->and(JournalAudit::where('action', 'carte.supprimee')->exists())->toBeFalse();
    });

    it('frees the card number and the phone for a real activation', function () {
        $carte = carteAvecHistorique();
        $telephone = $carte->titulaire->telephone;

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte);

        $titulaire = Titulaire::factory()->create(['telephone' => $telephone]);
        $nouvelle = Carte::factory()->for($titulaire)->create(['numero_carte' => $carte->numero_carte]);

        expect($nouvelle->exists)->toBeTrue()->and($nouvelle->operations()->count())->toBe(1);
    });

    it('keeps a holder who has another card, and the sms sent to that holder', function () {
        $carte = carteAvecHistorique();
        $autre = Carte::factory()->for($carte->titulaire)->revoquee()->create();

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte);

        expect(Titulaire::find($carte->titulaire_id))->not->toBeNull()
            ->and(Carte::find($autre->id))->not->toBeNull()
            ->and(SuppressionCarte::sole()->titulaire_supprime)->toBeFalse()
            // Seul le SMS de l'alerte de cette carte part avec elle.
            ->and(MessageSms::pluck('telephone')->all())->toBe([$carte->titulaire->telephone]);
    });

    it('leaves other cards and their history untouched', function () {
        $carte = carteAvecHistorique();
        $voisine = carteAvecHistorique();

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte);

        expect(Carte::find($voisine->id))->not->toBeNull()
            ->and($voisine->transactions()->count())->toBe(1)
            ->and($voisine->demandesOtp()->count())->toBe(2)
            ->and($voisine->alertesExpiration()->count())->toBe(1)
            ->and($voisine->operations()->count())->toBe(1)
            ->and(Titulaire::find($voisine->titulaire_id))->not->toBeNull();
    });

    it('keeps an sms still waiting to be sent, since its job references it', function () {
        $carte = Carte::factory()->create();
        $enAttente = smsPour($carte->titulaire->telephone, statut: StatutLivraison::EnAttente);

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte);

        expect(MessageSms::find($enAttente->id))->not->toBeNull();
    });

    it('refreshes the dashboard figures at once', function () {
        $carte = carteAvecHistorique();
        $avant = StatistiquesTableauDeBord::globales();

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte);

        expect(StatistiquesTableauDeBord::globales()['cartes_actives'])->toBe($avant['cartes_actives'] - 1)
            ->and(StatistiquesTableauDeBord::globales()['passages_du_jour'])->toBe($avant['passages_du_jour'] - 1);
    });

    it('deletes nothing when a step fails', function () {
        $carte = carteAvecHistorique();
        SuppressionCarte::creating(fn () => throw new RuntimeException('panne'));

        $this->withoutExceptionHandling();
        expect(fn () => supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte))->toThrow(RuntimeException::class, 'panne');

        expect(Carte::find($carte->id))->not->toBeNull()
            ->and($carte->transactions()->count())->toBe(1)
            ->and($carte->demandesOtp()->count())->toBe(2)
            ->and($carte->operations()->count())->toBe(1)
            ->and(Titulaire::find($carte->titulaire_id))->not->toBeNull()
            ->and(MessageSms::count())->toBe(2);
    });
});

describe('droits et confirmations', function () {
    it('is refused to roles that were not given the permission', function (Role $role) {
        $carte = carteAvecHistorique();
        $user = utilisateurAvecRole($role);

        avecPinRecent($user)->get(route('gestion.cartes.suppression', $carte))->assertForbidden();
        supprimerCarte($user, $carte)->assertForbidden();

        expect(Carte::find($carte->id))->not->toBeNull()->and(SuppressionCarte::count())->toBe(0);
    })->with([Role::Admin, Role::Agent, Role::Partenaire]);

    it('can be delegated by the superadmin to an admin', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $admin->givePermissionTo(Permission::SupprimerCarteDefinitivement->value);
        $carte = carteAvecHistorique();

        avecPinRecent($admin)->get(route('gestion.cartes.suppression', $carte))->assertOk();
        supprimerCarte($admin, $carte)->assertRedirect(route('gestion.cartes.index'));

        expect(SuppressionCarte::sole()->supprime_par_id)->toBe($admin->id);
    });

    it('is granted to no role by default', function () {
        expect(config('permissions.groupes.cartes.permissions.supprimer-carte-definitivement.roles'))->toBe([])
            ->and(utilisateurAvecRole(Role::Admin)->can(Permission::SupprimerCarteDefinitivement->value))->toBeFalse()
            ->and(utilisateurAvecRole(Role::Superadmin)->can(Permission::SupprimerCarteDefinitivement->value))->toBeTrue();
    });

    it('asks for the password again before showing the page or deleting', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);
        $carte = Carte::factory()->create();

        connecter($superadmin)->get(route('gestion.cartes.suppression', $carte))->assertRedirect(route('password.confirm'));
        connecter($superadmin)->delete(route('gestion.cartes.destroy', $carte), ['motif' => 'Carte de test', 'numero_confirmation' => $carte->numero_carte])
            ->assertRedirect(route('password.confirm'));

        expect(Carte::find($carte->id))->not->toBeNull();
    });

    it('requires a reason and the exact card number', function (array $saisie, string $champ) {
        $carte = carteAvecHistorique();

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte, $saisie)->assertSessionHasErrors($champ);

        expect(Carte::find($carte->id))->not->toBeNull()->and(SuppressionCarte::count())->toBe(0);
    })->with([
        'motif absent' => [['motif' => ''], 'motif'],
        'motif trop court' => [['motif' => 'test'], 'motif'],
        'numéro absent' => [['numero_confirmation' => ''], 'numero_confirmation'],
        'autre numéro' => [['numero_confirmation' => '9999999'], 'numero_confirmation'],
    ]);

    it('accepts the number typed with spaces, as printed on the card', function () {
        $carte = Carte::factory()->create();

        supprimerCarte(utilisateurAvecRole(Role::Superadmin), $carte, ['numero_confirmation' => $carte->numeroFormate()])
            ->assertSessionHasNoErrors();

        expect(Carte::find($carte->id))->toBeNull();
    });

    it('limits the number of deletions per minute', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        foreach (range(1, 5) as $i) {
            supprimerCarte($superadmin, Carte::factory()->create())->assertRedirect();
        }

        $sixieme = Carte::factory()->create();
        supprimerCarte($superadmin, $sixieme)->assertStatus(429);

        expect(Carte::find($sixieme->id))->not->toBeNull();
    });
});

describe('écrans', function () {
    it('shows what will be erased before asking for confirmation', function () {
        $carte = carteAvecHistorique();

        avecPinRecent(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.cartes.suppression', $carte))
            ->assertOk()
            ->assertSee('Action irréversible')
            ->assertSeeInOrder(['<strong>1</strong> transaction(s)', 'chez <strong>1</strong> partenaire(s)'], false)
            ->assertSee('<strong>2</strong> code(s) de validation', false)
            ->assertSee($carte->titulaire->nomComplet())
            ->assertSee('il n\'a aucune autre carte', false)
            ->assertSee('name="numero_confirmation"', false);
    });

    it('tells that a holder with another card is kept', function () {
        $carte = Carte::factory()->create();
        Carte::factory()->for($carte->titulaire)->revoquee()->create();

        avecPinRecent(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.cartes.suppression', $carte))
            ->assertOk()
            ->assertSee('est conservé : il a 1 autre(s) carte(s)');
    });

    it('shows the delete button on the card page only with the permission', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.cartes.show', $carte))
            ->assertSee(route('gestion.cartes.suppression', $carte));
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.show', $carte))
            ->assertDontSee(route('gestion.cartes.suppression', $carte));
    });

    it('lists the deleted cards for the superadmin, with author and reason', function () {
        $superadmin = utilisateurAvecRole(Role::Superadmin);
        $carte = carteAvecHistorique();
        supprimerCarte($superadmin, $carte, ['motif' => 'Essai de recette']);

        connecter($superadmin)->get(route('gestion.corbeille.index'))
            ->assertOk()
            ->assertSee('Cartes supprimées définitivement')
            ->assertSee($carte->numeroFormate())
            ->assertSee('Essai de recette')
            ->assertSee($superadmin->libelleActeur());
    });
});

describe('protections en base', function () {
    it('never lets the register be changed or erased', function () {
        supprimerCarte(utilisateurAvecRole(Role::Superadmin), Carte::factory()->create());
        $registre = SuppressionCarte::sole();

        expect(fn () => $registre->update(['motif' => 'Autre']))->toThrow(LogicException::class)
            ->and(fn () => $registre->delete())->toThrow(LogicException::class)
            ->and(fn () => SuppressionCarte::query()->toBase()->update(['motif' => 'Autre']))->toThrow(QueryException::class)
            ->and(fn () => SuppressionCarte::query()->toBase()->delete())->toThrow(QueryException::class);
    });

    it('keeps the operations history append-only outside this action', function () {
        $carte = Carte::factory()->create();

        expect(fn () => OperationCarte::query()->where('carte_id', $carte->id)->toBase()->delete())->toThrow(QueryException::class);

        // Le verrou est refermé après une suppression : une autre carte reste protégée.
        supprimerCarte(utilisateurAvecRole(Role::Superadmin), Carte::factory()->create());

        expect(fn () => OperationCarte::query()->where('carte_id', $carte->id)->toBase()->delete())->toThrow(QueryException::class)
            ->and($carte->operations()->count())->toBe(1);
    });
});
