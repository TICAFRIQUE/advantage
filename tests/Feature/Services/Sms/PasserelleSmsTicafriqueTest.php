<?php

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\MessageSms;
use App\Services\Sms\EnvoiSms;
use App\Services\Sms\PasserelleSms;
use App\Services\Sms\PasserelleSmsTicafrique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const URL_TICAFRIQUE = 'https://sms.ticafrique.ci/api/v1/sms/send';

function passerelleTicafrique(): PasserelleSmsTicafrique
{
    return new PasserelleSmsTicafrique(URL_TICAFRIQUE, 'cle-secrete-test', 'FONTAINE G');
}

function configurerTicafrique(): void
{
    config([
        'plateforme.sms.driver' => 'ticafrique',
        'services.ticafrique.url' => URL_TICAFRIQUE,
        'services.ticafrique.cle' => 'cle-secrete-test',
        'services.ticafrique.expediteur' => 'FONTAINE G',
    ]);
}

it('sends the message with the bearer key, the E.164 number and the sender id', function () {
    Http::fake([URL_TICAFRIQUE => Http::response(['success' => true, 'data' => ['message_id' => 'TIC-123']])]);

    $resultat = passerelleTicafrique()->envoyer('+2250707123456', 'Votre code : 123456');

    expect($resultat->succes)->toBeTrue()->and($resultat->reference)->toBe('TIC-123');
    Http::assertSent(fn (Request $requete) => $requete->url() === URL_TICAFRIQUE
        && $requete->method() === 'POST'
        && $requete->hasHeader('Authorization', 'Bearer cle-secrete-test')
        && $requete->data() === ['to' => '+2250707123456', 'message' => 'Votre code : 123456', 'sender_id' => 'FONTAINE G']);
});

it('reports a refusal with the provider message but never the number', function (int $statut, array $corps, string $attendu) {
    Http::fake([URL_TICAFRIQUE => Http::response($corps, $statut)]);

    $resultat = passerelleTicafrique()->envoyer('+2250707123456', 'Bonjour');

    expect($resultat->succes)->toBeFalse()
        ->and($resultat->erreur)->toBe($attendu)
        ->and($resultat->erreur)->not->toContain('0707123456');
})->with([
    'solde insuffisant' => [200, ['success' => false, 'message' => 'Solde insuffisant'], 'Refus du fournisseur (HTTP 200) : Solde insuffisant'],
    'clé invalide' => [401, ['success' => false, 'message' => 'Unauthorized'], 'Refus du fournisseur (HTTP 401) : Unauthorized'],
    'erreur serveur sans corps' => [500, [], 'Refus du fournisseur (HTTP 500).'],
    'succès sans identifiant' => [200, ['success' => true, 'data' => []], 'Refus du fournisseur (HTTP 200).'],
]);

it('reports an unreachable provider without throwing', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(passerelleTicafrique()->envoyer('+2250707123456', 'Bonjour'))
        ->succes->toBeFalse()
        ->erreur->toBe('Fournisseur SMS injoignable (délai dépassé ou réseau).');
});

it('refuses a missing key or a non-HTTPS url', function (string $url, string $cle) {
    expect(fn () => new PasserelleSmsTicafrique($url, $cle, 'FONTAINE G'))->toThrow(InvalidArgumentException::class);
})->with([
    'clé absente' => [URL_TICAFRIQUE, ''],
    'url absente' => ['', 'cle'],
    'url en clair' => ['http://sms.ticafrique.ci/api/v1/sms/send', 'cle'],
]);

it('is selected by SMS_DRIVER=ticafrique', function () {
    configurerTicafrique();

    expect(app(PasserelleSms::class))->toBeInstanceOf(PasserelleSmsTicafrique::class);
});

it('delivers a queued OTP through TICAFRIQUE and masks the code afterwards', function () {
    configurerTicafrique();
    Http::fake([URL_TICAFRIQUE => Http::response(['success' => true, 'data' => ['message_id' => 'TIC-OTP-1']])]);

    app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre code ADVANTAGE : 482913', TypeSms::Otp);

    $message = MessageSms::sole();

    expect($message)
        ->statut->toBe(StatutLivraison::Envoyee)
        ->fournisseur->toBe('ticafrique')
        ->reference_fournisseur->toBe('TIC-OTP-1')
        ->contenu->toBe('[contenu masqué après envoi]');
    Http::assertSentCount(1);
});

it('lets the operator test the configuration from the command line', function () {
    configurerTicafrique();
    Http::fake([URL_TICAFRIQUE => Http::response(['success' => true, 'data' => ['message_id' => 'TIC-TEST']])]);

    $this->artisan('sms:tester', ['telephone' => '0707123456'])
        ->expectsOutputToContain('référence TIC-TEST')
        ->assertSuccessful();

    Http::assertSent(fn (Request $requete) => $requete['to'] === '+2250707123456' && ! str_contains($requete['message'], 'code'));
});

it('fails clearly when the configuration is incomplete', function () {
    configurerTicafrique();
    config(['services.ticafrique.cle' => '']);

    $this->artisan('sms:tester', ['telephone' => '0707123456'])
        ->expectsOutputToContain('Configuration SMS invalide')
        ->assertFailed();
});
