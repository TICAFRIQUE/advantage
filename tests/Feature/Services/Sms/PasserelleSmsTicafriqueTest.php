<?php

use App\Actions\Partenaire\DemanderOtpAction;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\MessageSms;
use App\Models\Partenaire;
use App\Services\Sms\EnvoiSms;
use App\Services\Sms\PasserelleSms;
use App\Services\Sms\PasserelleSmsTicafrique;
use App\Services\Sms\TexteSms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    'succès déclaré faux' => [200, ['success' => false, 'message' => 'SMS sent successfully'], 'Refus du fournisseur (HTTP 200) : SMS sent successfully'],
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

it('never turns a declared success into a failure, whatever the response shape', function (array $corps, ?string $reference) {
    Http::fake([URL_TICAFRIQUE => Http::response($corps)]);
    Log::spy();

    $resultat = passerelleTicafrique()->envoyer('+2250707123456', 'Bonjour');

    expect($resultat->succes)->toBeTrue();

    if ($reference !== null) {
        expect($resultat->reference)->toBe($reference);
        Log::shouldNotHaveReceived('warning');
    } else {
        // Référence locale ; la structure (sans valeurs) est journalisée pour diagnostic.
        expect($resultat->reference)->toStartWith('TICAFRIQUE-');
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $contexte) => ! str_contains(json_encode($contexte), '0707123456'));
    }
})->with([
    'réponse réelle TICAFRIQUE' => [['success' => true, 'message' => 'SMS sent successfully', 'data' => ['message_ids' => ['TIC-REEL-1'], 'recipient' => '+2250707123456', 'parts_sent' => 1, 'total_segments' => 1, 'total_cost' => 1, 'currency' => 'XOF']], 'TIC-REEL-1'],
    'message seul' => [['message' => 'SMS sent successfully'], null],
    'succès sans identifiant' => [['success' => true, 'data' => []], null],
    'succès texte' => [['success' => 'true', 'data' => ['id' => 987]], '987'],
    'statut success' => [['status' => 'success', 'message_id' => 'M-1'], 'M-1'],
    'identifiant en liste' => [['success' => 1, 'data' => [['message_id' => 'L-1']]], 'L-1'],
]);

it('keeps OTP and expiry SMS within one unit, even with an accented partner name', function () {
    $partenaire = Partenaire::factory()->create(['nom' => 'Hôtel Pâtisserie Brûlée du Plateau Côte']);
    $methode = new ReflectionMethod(DemanderOtpAction::class, 'message');
    $otp = $methode->invoke(app(DemanderOtpAction::class), '482913', $partenaire);

    $message = app(EnvoiSms::class)->envoyer('+2250707123456', $otp, TypeSms::Otp);
    $alerte = strtr((string) config('plateforme.alertes_expiration.message'), [':numero' => '123 456 7', ':date' => '31/12/2026', ':delai' => '3 mois']);

    expect($message->fresh()->contenu)->toContain('Hotel Patisserie Brulée')
        ->and(TexteSms::segments($message->fresh()->contenu))->toBe(1)
        ->and(TexteSms::segments(TexteSms::normaliser($alerte)))->toBe(1);
});
