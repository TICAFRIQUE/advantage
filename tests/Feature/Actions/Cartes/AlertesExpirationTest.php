<?php

use App\Actions\Cartes\EnvoyerAlertesExpiration;
use App\Actions\Cartes\MarquerCartesExpirees;
use App\Enums\CanalAlerte;
use App\Enums\PalierAlerte;
use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Enums\StatutLivraison;
use App\Enums\TypeOperationCarte;
use App\Enums\TypeSms;
use App\Models\AlerteExpiration;
use App\Models\Carte;
use App\Models\MessageSms;
use App\Models\OperationCarte;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Carte active expirant dans $jours jours (expire_le dérive de active_le + 1 an).
 */
function carteExpirantDans(int $jours, array $attributs = []): Carte
{
    return Carte::factory()->create($attributs + ['active_le' => now()->subYear()->addDays($jours)]);
}

function alerter(): array
{
    return app(EnvoyerAlertesExpiration::class)();
}

describe('alertes SMS', function () {
    it('sends the alert of the nearest tier, once, with the card number and date', function (int $jours, PalierAlerte $palier) {
        $carte = carteExpirantDans($jours);

        expect(alerter()[$palier->value])->toBe(1)
            ->and(alerter()[$palier->value])->toBe(0);

        $alerte = AlerteExpiration::sole();
        $message = MessageSms::sole();

        expect($alerte)
            ->carte_id->toBe($carte->id)
            ->palier->toBe($palier)
            ->canal->toBe(CanalAlerte::Sms)
            ->message_sms_id->toBe($message->id)
            ->and($message->type)->toBe(TypeSms::AlerteExpiration)
            ->and($message->telephone)->toBe($carte->titulaire->telephone)
            ->and($message->contenu)->toContain($carte->numeroFormate(), $carte->expire_le->format('d/m/Y'), $palier->libelle())
            ->and(mb_strlen($message->contenu))->toBeLessThanOrEqual(160)
            ->and($alerte->statutLivraison())->toBe(StatutLivraison::Envoyee);
    })->with([
        '3 mois' => [80, PalierAlerte::TroisMois],
        '2 mois' => [50, PalierAlerte::DeuxMois],
        '1 mois' => [20, PalierAlerte::UnMois],
    ]);

    it('follows a card through the three tiers, one SMS each', function () {
        carteExpirantDans(85);

        alerter();
        $this->travel(30)->days();
        alerter();
        alerter();
        $this->travel(30)->days();
        alerter();

        expect(AlerteExpiration::orderBy('id')->pluck('palier')->all())->toBe([PalierAlerte::TroisMois, PalierAlerte::DeuxMois, PalierAlerte::UnMois])
            ->and(MessageSms::count())->toBe(3);
    });

    it('sends only the nearest tier when earlier ones were missed', function () {
        carteExpirantDans(20);

        alerter();

        expect(AlerteExpiration::sole()->palier)->toBe(PalierAlerte::UnMois)
            ->and(MessageSms::count())->toBe(1);
    });

    it('ignores cards far from expiry, expired, suspended or revoked', function () {
        carteExpirantDans(120);
        carteExpirantDans(-2);
        carteExpirantDans(20, ['statut' => StatutCarte::Suspendue]);
        carteExpirantDans(20, ['statut' => StatutCarte::Revoquee]);

        expect(array_sum(alerter()))->toBe(0)
            ->and(MessageSms::count())->toBe(0);
    });

    it('sends nothing when SMS alerts are disabled', function () {
        config(['plateforme.alertes_expiration.sms' => false]);
        carteExpirantDans(20);

        $this->artisan('cartes:alertes-expiration')->expectsOutputToContain('désactivées')->assertSuccessful();

        expect(MessageSms::count())->toBe(0);
    });

    it('never sends the same alert twice, even concurrently', function () {
        $carte = carteExpirantDans(20);
        AlerteExpiration::create(['carte_id' => $carte->id, 'palier' => PalierAlerte::UnMois, 'canal' => CanalAlerte::Sms, 'envoyee_le' => now()]);

        alerter();

        expect(AlerteExpiration::count())->toBe(1)->and(MessageSms::count())->toBe(0);
    });

    it('reports its work from the command line', function () {
        carteExpirantDans(20);

        $this->artisan('cartes:alertes-expiration')->expectsOutputToContain('1 mois')->assertSuccessful();
    });
});

describe('expiration automatique', function () {
    it('marks outdated active cards as expired, as a system operation', function () {
        $echue = carteExpirantDans(-1);
        $valide = carteExpirantDans(30);
        $suspendue = carteExpirantDans(-1, ['statut' => StatutCarte::Suspendue]);

        $this->artisan('cartes:marquer-expirees')->expectsOutputToContain('1 carte(s)')->assertSuccessful();

        $operation = OperationCarte::where('carte_id', $echue->id)->where('type', TypeOperationCarte::Expiration)->sole();

        expect($echue->fresh()->statut)->toBe(StatutCarte::Expiree)
            ->and($valide->fresh()->statut)->toBe(StatutCarte::Active)
            ->and($suspendue->fresh()->statut)->toBe(StatutCarte::Suspendue)
            ->and($operation->effectuee_par_id)->toBeNull();

        expect(app(MarquerCartesExpirees::class)())->toBe(0);
    });

    it('schedules both tasks every day, alerts during the day', function () {
        $taches = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [$e->command => $e->expression]);

        expect($taches->first(fn ($x, $commande) => str_contains($commande, 'cartes:marquer-expirees')))->toBe('10 0 * * *')
            ->and($taches->first(fn ($x, $commande) => str_contains($commande, 'cartes:alertes-expiration')))->toBe('0 9 * * *');
    });
});

describe('dans l\'application', function () {
    it('shows upcoming expirations on the dashboard', function () {
        $proche = carteExpirantDans(10);
        carteExpirantDans(50);
        carteExpirantDans(200);

        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))
            ->assertOk()
            ->assertSeeInOrder(['Cartes bientôt expirées', 'Dans le mois', '1', 'Sous 2 mois', '2', 'Sous 3 mois', '2'])
            ->assertSee($proche->numeroFormate());
    });

    it('lists the alerts sent on the card detail', function () {
        $carte = carteExpirantDans(20);
        alerter();

        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.show', $carte))
            ->assertSee('Alertes d\'expiration envoyées', false)
            ->assertSee('SMS · échéance dans 1 mois');
    });
});
