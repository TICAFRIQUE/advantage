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
        ->post(route('partenaire.verifier.store'), ['numero_carte' => '456 789 0']);

    $verification->assertSee('Carte valide')->assertSee('10 %')
        ->assertDontSee('KONAN')->assertDontSee('Yao Serge')->assertDontSee('07 07 12 34 56');

    $envoi = connecter($operateur)->post(route('partenaire.codes.store'), ['numero_carte' => '4567890']);
    $demande = DemandeOtp::sole();
    $envoi->assertRedirect(route('partenaire.codes.show', $demande));

    connecter($operateur)->get(route('partenaire.codes.show', $demande))
        ->assertOk()->assertSee('456 789 0')->assertDontSee('KONAN');

    $validation = connecter($operateur)->post(route('partenaire.codes.valider', $demande), ['code' => dernierCodeEnvoye()]);
    $transaction = Transaction::sole();
    $validation->assertRedirect(route('partenaire.transactions.show', $transaction));

    connecter($operateur)->get(route('partenaire.transactions.show', $transaction))
        ->assertOk()->assertSee('Remise de 10 % accordée')->assertSee('Yao Serge KONAN');
});

it('gives the same answer for unknown and unusable cards', function (Closure $preparer) {
    $numero = $preparer();

    connecter(utilisateurAvecRole(Role::Partenaire))->followingRedirects()
        ->post(route('partenaire.verifier.store'), ['numero_carte' => $numero])
        ->assertSee('Carte non valide')
        ->assertDontSee('Carte valide ·');
})->with([
    'inconnue' => [fn () => '9999999'],
    'expirée' => [fn () => Carte::factory()->expiree()->create()->numero_carte],
    'suspendue' => [fn () => Carte::factory()->suspendue()->create()->numero_carte],
    'révoquée' => [fn () => Carte::factory()->revoquee()->create()->numero_carte],
]);

it('logs every verification with its outcome', function () {
    connecter(utilisateurAvecRole(Role::Partenaire))->post(route('partenaire.verifier.store'), ['numero_carte' => '9999999']);

    expect(JournalAudit::where('action', 'carte.verifiee')->sole()->donnees)
        ->toMatchArray(['numero_carte' => '9999999', 'valide' => false]);
});

it('rejects a malformed card number', function (string $numero) {
    connecter(utilisateurAvecRole(Role::Partenaire))
        ->post(route('partenaire.verifier.store'), ['numero_carte' => $numero])
        ->assertSessionHasErrors('numero_carte');
})->with(['123456', '12345678', 'ABCDEFG', '']);

it('rejects a malformed code', function (string $code) {
    [$demande, $operateur] = [DemandeOtp::factory()->create(), null];
    $operateur = utilisateurAvecRole(Role::Partenaire, ['partenaire_id' => $demande->partenaire_id]);

    connecter($operateur)->post(route('partenaire.codes.valider', $demande), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($demande->fresh()->tentatives)->toBe(0);
})->with(['12345', '1234567', 'abcdef']);

it('creates a single transaction when the code form is submitted twice', function () {
    $carte = carteDeKonan();
    $operateur = utilisateurAvecRole(Role::Partenaire);
    connecter($operateur)->post(route('partenaire.codes.store'), ['numero_carte' => $carte->numero_carte]);
    $demande = DemandeOtp::sole();
    $code = dernierCodeEnvoye();

    connecter($operateur)->post(route('partenaire.codes.valider', $demande), ['code' => $code]);
    connecter($operateur)->post(route('partenaire.codes.valider', $demande), ['code' => $code])
        ->assertRedirect(route('partenaire.transactions.show', Transaction::sole()));
});

it('forbids a partner from using or viewing another partner code and transaction', function () {
    $demande = DemandeOtp::factory()->create();
    $transaction = Transaction::factory()->create();
    $intrus = utilisateurAvecRole(Role::Partenaire);

    connecter($intrus)->get(route('partenaire.codes.show', $demande))->assertForbidden();
    connecter($intrus)->post(route('partenaire.codes.valider', $demande), ['code' => '123456'])->assertForbidden();
    connecter($intrus)->get(route('partenaire.transactions.show', $transaction))->assertForbidden();

    expect($demande->fresh()->tentatives)->toBe(0);
});

it('limits card verifications per operator', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);

    foreach (range(1, 20) as $essai) {
        connecter($operateur)->post(route('partenaire.verifier.store'), ['numero_carte' => '9999999']);
    }

    connecter($operateur)->post(route('partenaire.verifier.store'), ['numero_carte' => '9999999'])->assertTooManyRequests();
});

it('lets a new code be requested only after the waiting time', function () {
    $carte = carteDeKonan();
    $operateur = utilisateurAvecRole(Role::Partenaire);
    connecter($operateur)->post(route('partenaire.codes.store'), ['numero_carte' => $carte->numero_carte]);
    $premiere = DemandeOtp::sole();

    connecter($operateur)->post(route('partenaire.codes.renvoyer', $premiere))->assertSessionHas('erreur');
    expect(DemandeOtp::count())->toBe(1);

    $this->travel(61)->seconds();
    connecter($operateur)->post(route('partenaire.codes.renvoyer', $premiere))->assertSessionHas('succes');

    expect(DemandeOtp::count())->toBe(2)
        ->and($premiere->fresh()->statut)->toBe(StatutDemandeOtp::Expiree);
});

describe('admin agissant pour un partenaire (option A)', function () {
    it('sends the admin to the partner selector first', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('partenaire.verifier'))
            ->assertRedirect(route('partenaire.tableau-de-bord'))
            ->assertSessionHas('erreur');
    });

    it('records the transaction for the chosen partner and in the admin name', function () {
        $carte = carteDeKonan();
        $partenaire = Partenaire::factory()->create(['nom' => 'Hôtel Ivoire', 'taux_reduction' => 20]);
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);

        connecter($admin)->post(route('partenaire.partenaire-courant.store'), ['partenaire_id' => $partenaire->id])
            ->assertSessionHas(PartenaireCourant::CLE_SESSION, $partenaire->id);

        $session = [PartenaireCourant::CLE_SESSION => $partenaire->id];
        connecter($admin)->withSession($session)->post(route('partenaire.codes.store'), ['numero_carte' => $carte->numero_carte]);
        connecter($admin)->withSession($session)
            ->post(route('partenaire.codes.valider', DemandeOtp::sole()), ['code' => dernierCodeEnvoye()]);

        expect(Transaction::sole())
            ->partenaire_id->toBe($partenaire->id)
            ->valide_par_id->toBe($admin->id)
            ->taux_applique->toBe('20.00')
            ->and(JournalAudit::where('action', 'partenaire.choisi')->sole()->acteur_id)->toBe($admin->id);

        connecter($admin)->withSession($session)->get(route('partenaire.transactions.show', Transaction::sole()))
            ->assertSee('Awa Koné · Administrateur');
    });

    it('cannot choose an inactive partner', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('partenaire.partenaire-courant.store'), ['partenaire_id' => Partenaire::factory()->inactif()->create()->id])
            ->assertSessionHasErrors('partenaire_id');
    });

    it('does not let a partner operator switch partner', function () {
        connecter(utilisateurAvecRole(Role::Partenaire))
            ->post(route('partenaire.partenaire-courant.store'), ['partenaire_id' => Partenaire::factory()->create()->id])
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
