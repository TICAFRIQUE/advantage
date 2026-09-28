<?php

use App\Actions\Partenaire\DemanderOtpAction;
use App\Enums\Role;
use App\Enums\StatutDemandeOtp;
use App\Enums\TypeSms;
use App\Exceptions\OperationPartenaireException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use App\Models\Partenaire;
use App\Models\Titulaire;
use Illuminate\Support\Facades\Hash;

function demanderCode(Carte $carte, ?Partenaire $partenaire = null): DemandeOtp
{
    $operateur = utilisateurAvecRole(Role::Partenaire, $partenaire ? ['partenaire_id' => $partenaire->id] : []);
    test()->actingAs($operateur);

    return app(DemanderOtpAction::class)($carte->numero_carte, $operateur->partenaire, $operateur);
}

it('creates a pending five-minute code and texts it to the holder with the partner name', function () {
    $this->freezeTime();
    $titulaire = Titulaire::factory()->create(['telephone' => '+2250707123456']);
    $carte = Carte::factory()->for($titulaire)->create();
    $partenaire = Partenaire::factory()->create(['nom' => 'Pharmacie du Plateau']);

    $demande = demanderCode($carte, $partenaire);

    $sms = MessageSms::sole();
    preg_match('/\b(\d{6})\b/', $sms->contenu, $code);

    expect($demande)
        ->statut->toBe(StatutDemandeOtp::EnAttente)
        ->partenaire_id->toBe($partenaire->id)
        ->expire_le->toDateTimeString()->toBe(now()->addMinutes(5)->toDateTimeString())
        ->and($sms->telephone)->toBe('+2250707123456')
        ->and($sms->type)->toBe(TypeSms::Otp)
        ->and($sms->contenu)->toContain('Pharmacie du Plateau')
        ->and(Hash::check($code[1], $demande->code_hash))->toBeTrue()
        ->and($demande->code_hash)->not->toContain($code[1]);
});

it('refuses a card that cannot be used, without sending anything', function (Closure $fabriquer) {
    $carte = $fabriquer();

    expect(fn () => demanderCode($carte))->toThrow(OperationPartenaireException::class, 'Carte non valide.')
        ->and(MessageSms::count())->toBe(0);
})->with([
    'expirée' => [fn () => Carte::factory()->expiree()->create()],
    'suspendue' => [fn () => Carte::factory()->suspendue()->create()],
    'révoquée' => [fn () => Carte::factory()->revoquee()->create()],
    'date échue' => [fn () => Carte::factory()->activeeIlYa(12, 1)->create()],
    'inconnue' => [fn () => Carte::factory()->make(['numero_carte' => '9999999'])],
]);

it('cancels the previous pending code of the same partner', function () {
    $carte = Carte::factory()->create();
    $partenaire = Partenaire::factory()->create();

    $premiere = demanderCode($carte, $partenaire);
    $seconde = demanderCode($carte, $partenaire);

    expect($premiere->fresh()->statut)->toBe(StatutDemandeOtp::Expiree)
        ->and($seconde->fresh()->statut)->toBe(StatutDemandeOtp::EnAttente);
});

it('limits codes per card to protect the holder from sms harassment', function () {
    $carte = Carte::factory()->create();

    foreach (range(1, 3) as $essai) {
        demanderCode($carte, Partenaire::factory()->create());
    }

    expect(fn () => demanderCode($carte))->toThrow(OperationPartenaireException::class, 'Trop de codes demandés')
        ->and(MessageSms::count())->toBe(3);

    $this->travel(16)->minutes();

    expect(demanderCode($carte)->statut)->toBe(StatutDemandeOtp::EnAttente);
});

it('caps codes per card per day', function () {
    $carte = Carte::factory()->create();

    foreach (range(1, 10) as $essai) {
        demanderCode($carte);
        $this->travel(16)->minutes();
    }

    expect(fn () => demanderCode($carte))->toThrow(OperationPartenaireException::class, 'Trop de codes demandés');
});

it('logs the request without the code', function () {
    $demande = demanderCode(Carte::factory()->create());
    preg_match('/\b(\d{6})\b/', MessageSms::sole()->contenu, $code);

    $entree = JournalAudit::where('action', 'otp.demande')->sole();

    expect($entree->entite_id)->toBe($demande->id)
        ->and(json_encode($entree->donnees))->not->toContain($code[1]);
});
