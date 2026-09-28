<?php

use App\Enums\Role;
use App\Enums\StatutDemandeOtp;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use App\Models\Partenaire;
use App\Models\Titulaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartenaireCourant;

function carteDeKonan(): Carte
{
    $titulaire = Titulaire::factory()->create(['nom' => 'KONAN', 'prenom' => 'Yao Serge', 'telephone' => '+2250707123456']);

    return Carte::factory()->for($titulaire)->create(['numero_carte' => '4567890']);
}

function dernierCodeEnvoye(): string
{
    preg_match('/\b(\d{6})\b/', MessageSms::latest('id')->first()->contenu, $code);

    return $code[1];
}

it('runs the whole checkout flow and reveals the holder only after the code', function () {
    carteDeKonan();
    $operateur = utilisateurAvecRole(Role::Partenaire);
    $operateur->partenaire->update(['taux_reduction' => 10]);

    $verification = connecter($operateur)->followingRedirects()
        ->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '456 789 0']);

    $verification->assertSee('Carte valide')->assertSee('10 %')
        ->assertDontSee('KONAN')->assertDontSee('Yao Serge')->assertDontSee('07 07 12 34 56');

    $envoi = connecter($operateur)->post(route('partenaire.transaction.codes.store'), ['numero_carte' => '4567890']);
    $demande = DemandeOtp::sole();
    $envoi->assertRedirect(route('partenaire.transaction.codes.show', $demande));

    connecter($operateur)->get(route('partenaire.transaction.codes.show', $demande))
        ->assertOk()->assertSee('456 789 0')->assertDontSee('KONAN');

    $validation = connecter($operateur)->post(route('partenaire.transaction.codes.valider', $demande), ['code' => dernierCodeEnvoye()]);
    $transaction = Transaction::sole();
    $validation->assertRedirect(route('partenaire.transaction.resultat', $transaction));

    connecter($operateur)->get(route('partenaire.transaction.resultat', $transaction))
        ->assertOk()->assertSee('Remise de 10 % accordée')->assertSee('Yao Serge KONAN');
});

it('gives the same answer for unknown and unusable cards', function (Closure $preparer) {
    $numero = $preparer();

    connecter(utilisateurAvecRole(Role::Partenaire))->followingRedirects()
        ->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => $numero])
        ->assertSee('Carte non valide')
        ->assertDontSee('Carte valide ·');
})->with([
    'inconnue' => [fn () => '9999999'],
    'expirée' => [fn () => Carte::factory()->expiree()->create()->numero_carte],
    'suspendue' => [fn () => Carte::factory()->suspendue()->create()->numero_carte],
    'révoquée' => [fn () => Carte::factory()->revoquee()->create()->numero_carte],
]);

it('logs every verification with its outcome', function () {
    connecter(utilisateurAvecRole(Role::Partenaire))->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '9999999']);

    expect(JournalAudit::where('action', 'carte.verifiee')->sole()->donnees)
        ->toMatchArray(['numero_carte' => '9999999', 'valide' => false]);
});

it('rejects a malformed card number', function (string $numero) {
    connecter(utilisateurAvecRole(Role::Partenaire))
        ->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => $numero])
        ->assertSessionHasErrors('numero_carte');
})->with(['123456', '12345678', 'ABCDEFG', '']);

it('rejects a malformed code', function (string $code) {
    [$demande, $operateur] = [DemandeOtp::factory()->create(), null];
    $operateur = utilisateurAvecRole(Role::Partenaire, ['partenaire_id' => $demande->partenaire_id]);

    connecter($operateur)->post(route('partenaire.transaction.codes.valider', $demande), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($demande->fresh()->tentatives)->toBe(0);
})->with(['12345', '1234567', 'abcdef']);

it('creates a single transaction when the code form is submitted twice', function () {
    $carte = carteDeKonan();
    $operateur = utilisateurAvecRole(Role::Partenaire);
    connecter($operateur)->post(route('partenaire.transaction.codes.store'), ['numero_carte' => $carte->numero_carte]);
    $demande = DemandeOtp::sole();
    $code = dernierCodeEnvoye();

    connecter($operateur)->post(route('partenaire.transaction.codes.valider', $demande), ['code' => $code]);
    connecter($operateur)->post(route('partenaire.transaction.codes.valider', $demande), ['code' => $code])
        ->assertRedirect(route('partenaire.transaction.resultat', Transaction::sole()));
});

it('forbids a partner from using or viewing another partner code and transaction', function () {
    $demande = DemandeOtp::factory()->create();
    $transaction = Transaction::factory()->create();
    $intrus = utilisateurAvecRole(Role::Partenaire);

    connecter($intrus)->get(route('partenaire.transaction.codes.show', $demande))->assertForbidden();
    connecter($intrus)->post(route('partenaire.transaction.codes.valider', $demande), ['code' => '123456'])->assertForbidden();
    connecter($intrus)->get(route('partenaire.transaction.resultat', $transaction))->assertForbidden();

    expect($demande->fresh()->tentatives)->toBe(0);
});

it('limits card verifications per operator', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);

    foreach (range(1, 20) as $essai) {
        connecter($operateur)->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '9999999']);
    }

    connecter($operateur)->post(route('partenaire.transaction.verifier.store'), ['numero_carte' => '9999999'])->assertTooManyRequests();
});

it('lets a new code be requested only after the waiting time', function () {
    $carte = carteDeKonan();
    $operateur = utilisateurAvecRole(Role::Partenaire);
    connecter($operateur)->post(route('partenaire.transaction.codes.store'), ['numero_carte' => $carte->numero_carte]);
    $premiere = DemandeOtp::sole();

    connecter($operateur)->post(route('partenaire.transaction.codes.renvoyer', $premiere))->assertSessionHas('erreur');
    expect(DemandeOtp::count())->toBe(1);

    $this->travel(61)->seconds();
    connecter($operateur)->post(route('partenaire.transaction.codes.renvoyer', $premiere))->assertSessionHas('succes');

    expect(DemandeOtp::count())->toBe(2)
        ->and($premiere->fresh()->statut)->toBe(StatutDemandeOtp::Expiree);
});

describe('back-office agissant pour un partenaire (option A)', function () {
    it('shows the partner selector before any transaction', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.transaction.verifier'))
            ->assertOk()
            ->assertSee('Pour quel partenaire effectuez-vous cette transaction')
            ->assertSee('Continuer')
            ->assertDontSee('Numéro de la carte');
    });

    it('offers to change partner on the same page, never through the partner space', function () {
        $partenaire = Partenaire::factory()->create(['nom' => 'Hôtel Ivoire']);

        connecter(utilisateurAvecRole(Role::Admin))
            ->withSession([PartenaireCourant::CLE_SESSION => $partenaire->id])
            ->get(route('gestion.transaction.verifier'))
            ->assertOk()
            ->assertSee('Transaction pour le compte de')
            ->assertSee('Changer de partenaire')
            ->assertSee('Valider le changement')
            ->assertSee('Numéro de la carte')
            ->assertDontSee(route('partenaire.tableau-de-bord'), false);
    });

    it('refuses a verification before a partner is chosen', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.transaction.verifier.store'), ['numero_carte' => '1234567'])
            ->assertRedirect(route('gestion.transaction.verifier'))
            ->assertSessionHas('erreur');
    });

    it('records the transaction for the chosen partner and in the author name', function () {
        $carte = carteDeKonan();
        $partenaire = Partenaire::factory()->create(['nom' => 'Hôtel Ivoire', 'taux_reduction' => 20]);
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);

        connecter($admin)->post(route('gestion.transaction.partenaire-courant.store'), ['partenaire_id' => $partenaire->id])
            ->assertRedirect(route('gestion.transaction.verifier'))
            ->assertSessionHas(PartenaireCourant::CLE_SESSION, $partenaire->id);

        $session = [PartenaireCourant::CLE_SESSION => $partenaire->id];
        connecter($admin)->withSession($session)->post(route('gestion.transaction.codes.store'), ['numero_carte' => $carte->numero_carte])
            ->assertRedirect(route('gestion.transaction.codes.show', DemandeOtp::sole()));
        connecter($admin)->withSession($session)
            ->post(route('gestion.transaction.codes.valider', DemandeOtp::sole()), ['code' => dernierCodeEnvoye()])
            ->assertRedirect(route('gestion.transaction.resultat', Transaction::sole()));

        expect(Transaction::sole())
            ->partenaire_id->toBe($partenaire->id)
            ->valide_par_id->toBe($admin->id)
            ->taux_applique->toBe('20.00')
            ->and(JournalAudit::where('action', 'partenaire.choisi')->sole()->acteur_id)->toBe($admin->id);

        connecter($admin)->withSession($session)->get(route('gestion.transaction.resultat', Transaction::sole()))
            ->assertSee('Awa Koné · Administrateur');
    });

    it('lets an agent granted the permission make a transaction', function () {
        $carte = carteDeKonan();
        $partenaire = Partenaire::factory()->create();
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo('effectuer-transaction-partenaire');
        $session = [PartenaireCourant::CLE_SESSION => $partenaire->id];

        connecter($agent->fresh())->withSession($session)->post(route('gestion.transaction.codes.store'), ['numero_carte' => $carte->numero_carte]);
        connecter($agent->fresh())->withSession($session)
            ->post(route('gestion.transaction.codes.valider', DemandeOtp::sole()), ['code' => dernierCodeEnvoye()]);

        expect(Transaction::sole()->valide_par_id)->toBe($agent->id);
    });

    it('forbids transactions to an agent without the permission', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('gestion.transaction.partenaire-courant.store'), ['partenaire_id' => Partenaire::factory()->create()->id])
            ->assertForbidden();
    });

    it('cannot choose an inactive partner', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.transaction.partenaire-courant.store'), ['partenaire_id' => Partenaire::factory()->inactif()->create()->id])
            ->assertSessionHasErrors('partenaire_id');
    });

    it('does not let a partner operator switch partner', function () {
        connecter(utilisateurAvecRole(Role::Partenaire))
            ->post(route('gestion.transaction.partenaire-courant.store'), ['partenaire_id' => Partenaire::factory()->create()->id])
            ->assertForbidden();
    });

    it('ignores a partner forced into the session of an operator', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $autre = Partenaire::factory()->create();

        expect(PartenaireCourant::pour($operateur))->not->toBeNull();

        session([PartenaireCourant::CLE_SESSION => $autre->id]);

        expect(PartenaireCourant::pour(User::find($operateur->id))->id)->toBe($operateur->partenaire_id);
    });
});
