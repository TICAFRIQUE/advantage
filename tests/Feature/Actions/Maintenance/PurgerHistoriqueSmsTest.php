<?php

use App\Actions\Maintenance\PurgerHistoriqueSms;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use Illuminate\Console\Scheduling\Schedule;

function smsAPurger(StatutLivraison $statut = StatutLivraison::Envoyee): MessageSms
{
    return MessageSms::create([
        'telephone' => '+2250707123456', 'type' => TypeSms::Otp, 'contenu' => 'Code',
        'statut' => $statut, 'fournisseur' => 'simulation',
    ]);
}

it('keeps only the most recent sms', function () {
    $messages = collect(range(1, 60))->map(fn () => smsAPurger());

    $this->artisan('sms:purger-historique')->expectsOutputToContain('SMS supprimés')->assertSuccessful();

    expect(MessageSms::count())->toBe(50)
        ->and(MessageSms::min('id'))->toBe($messages[10]->id)
        ->and(JournalAudit::where('action', 'donnees.purgees')->sole()->donnees)->toBe(['messages_sms' => 10]);
});

it('never deletes a pending sms, however old', function () {
    $enAttente = smsAPurger(StatutLivraison::EnAttente);
    $echec = smsAPurger(StatutLivraison::Echec);
    collect(range(1, 50))->each(fn () => smsAPurger());

    expect(app(PurgerHistoriqueSms::class)())->toBe(1)
        ->and(MessageSms::whereKey($enAttente->id)->exists())->toBeTrue()
        ->and(MessageSms::whereKey($echec->id)->exists())->toBeFalse();
});

it('does nothing while the history is short enough', function () {
    collect(range(1, 50))->each(fn () => smsAPurger());

    expect(app(PurgerHistoriqueSms::class)())->toBe(0)
        ->and(MessageSms::count())->toBe(50)
        ->and(JournalAudit::where('action', 'donnees.purgees')->exists())->toBeFalse();
});

it('follows the configured number of sms to keep', function () {
    config(['plateforme.retention.messages_sms_conserver' => 3]);
    collect(range(1, 8))->each(fn () => smsAPurger());

    expect(app(PurgerHistoriqueSms::class)())->toBe(5)->and(MessageSms::count())->toBe(3);
});

it('runs every monday night', function () {
    $taches = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [$e->command => $e->expression]);

    expect($taches->first(fn ($x, $commande) => str_contains($commande, 'sms:purger-historique')))->toBe('0 3 * * 1');
});
