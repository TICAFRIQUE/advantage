<?php

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Jobs\EnvoyerSmsJob;
use App\Models\MessageSms;
use App\Services\Sms\EnvoiSms;
use App\Services\Sms\PasserelleSms;
use App\Services\Sms\ResultatEnvoiSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Faux fournisseur « réel » (autre que la simulation) pour les tests.
 */
function fournisseurDeTest(bool $succes = true): PasserelleSms
{
    return new class($succes) implements PasserelleSms
    {
        public int $appels = 0;

        public function __construct(private bool $succes) {}

        public function nom(): string
        {
            return 'fournisseur-test';
        }

        public function envoyer(string $telephone, string $message): ResultatEnvoiSms
        {
            $this->appels++;

            return $this->succes ? ResultatEnvoiSms::reussi('REF-123') : ResultatEnvoiSms::echoue('Solde insuffisant');
        }
    };
}

it('records the message encrypted and queues it without the code in the job payload', function () {
    Queue::fake([EnvoyerSmsJob::class]);

    $message = app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre code ADVANTAGE : 482915', TypeSms::Otp);

    expect($message->statut)->toBe(StatutLivraison::EnAttente)
        ->and(DB::table('messages_sms')->value('contenu'))->not->toContain('482915');

    Queue::assertPushed(EnvoyerSmsJob::class, function (EnvoyerSmsJob $job) use ($message) {
        return $job->message->is($message) && ! str_contains(serialize($job), '482915');
    });
});

it('delivers through the simulation and keeps the text readable for testers', function () {
    $message = app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre code ADVANTAGE : 482915', TypeSms::Otp);

    expect($message->fresh())
        ->statut->toBe(StatutLivraison::Envoyee)
        ->fournisseur->toBe('simulation')
        ->reference_fournisseur->toStartWith('SIM-')
        ->contenu->toBe('Votre code ADVANTAGE : 482915')
        ->envoye_le->not->toBeNull();
});

it('masks a one-time code once really sent by a provider', function () {
    app()->instance(PasserelleSms::class, fournisseurDeTest());

    $otp = app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre code : 482915', TypeSms::Otp);
    $info = app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre carte expire bientôt', TypeSms::AlerteExpiration);

    expect($otp->fresh()->contenu)->toBe('[contenu masqué après envoi]')
        ->and($otp->fresh()->reference_fournisseur)->toBe('REF-123')
        ->and($info->fresh()->contenu)->toBe('Votre carte expire bientôt');
});

it('never sends the same message twice', function () {
    $fournisseur = fournisseurDeTest();
    app()->instance(PasserelleSms::class, $fournisseur);
    $message = app(EnvoiSms::class)->envoyer('+2250707123456', 'Bonjour', TypeSms::Information);

    (new EnvoyerSmsJob($message))->handle($fournisseur);

    expect($fournisseur->appels)->toBe(1);
});

it('records the provider error and marks the message failed after the last attempt', function () {
    $fournisseur = fournisseurDeTest(succes: false);
    $message = MessageSms::create([
        'telephone' => '+2250707123456', 'type' => TypeSms::Otp, 'contenu' => 'Code : 1',
        'statut' => StatutLivraison::EnAttente, 'fournisseur' => 'fournisseur-test',
    ]);
    $job = new EnvoyerSmsJob($message);

    expect(fn () => $job->handle($fournisseur))->toThrow(RuntimeException::class, 'Solde insuffisant');

    $job->failed(new RuntimeException('Solde insuffisant'));

    expect($message->fresh())
        ->statut->toBe(StatutLivraison::Echec)
        ->erreur->toBe('Solde insuffisant')
        ->tentatives->toBe(1);
});

it('refuses the simulation driver in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(PasserelleSms::class);
})->throws(RuntimeException::class, 'interdit en production');

it('refuses an unknown driver', function () {
    config(['plateforme.sms.driver' => 'inexistant']);

    app(PasserelleSms::class);
})->throws(InvalidArgumentException::class, 'Pilote SMS inconnu');
