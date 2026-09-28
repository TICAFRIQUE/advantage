<?php

use App\Actions\Audit\PurgerJournalAudit;
use App\Enums\Role;
use App\Enums\TypePurge;
use App\Exceptions\JournalAuditImmuableException;
use App\Models\JournalAudit;
use App\Models\PurgeJournalAudit;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function entreeAuditIlYa(int $jours): JournalAudit
{
    test()->travel(-$jours)->days();
    $entree = JournalAudit::create(['type_acteur' => 'systeme', 'action' => "test.il_y_a_{$jours}_jours"]);
    test()->travelBack();

    return $entree;
}

it('deletes only entries older than the limit and records the purge', function () {
    entreeAuditIlYa(20);
    entreeAuditIlYa(15);
    $recente = entreeAuditIlYa(3);
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    $supprimees = app(PurgerJournalAudit::class)(now()->subDays(14), TypePurge::Manuelle, $superadmin, 'Nettoyage mensuel');

    expect($supprimees)->toBe(2)
        ->and(JournalAudit::whereKey($recente->id)->exists())->toBeTrue()
        ->and(PurgeJournalAudit::sole())
        ->type->toBe(TypePurge::Manuelle)
        ->purge_par_id->toBe($superadmin->id)
        ->nombre_entrees->toBe(2)
        ->motif->toBe('Nettoyage mensuel');
});

it('keeps the audit log protected against raw deletions after a purge', function () {
    entreeAuditIlYa(20);
    app(PurgerJournalAudit::class)(now()->subDays(14), TypePurge::Automatique);
    entreeAuditIlYa(1);

    DB::table('journaux_audit')->delete();
})->throws(QueryException::class, 'journaux_audit est en ajout seul');

it('requires a reason for a manual purge', function () {
    app(PurgerJournalAudit::class)(now()->subDays(14), TypePurge::Manuelle);
})->throws(InvalidArgumentException::class, 'exige un motif');

it('refuses a limit in the future', function () {
    app(PurgerJournalAudit::class)(now()->addDay(), TypePurge::Automatique);
})->throws(InvalidArgumentException::class, 'futur');

it('never lets anyone alter or delete the purge register', function (Closure $tentative, string $exception) {
    app(PurgerJournalAudit::class)(now()->subDays(14), TypePurge::Automatique);

    expect($tentative)->toThrow($exception);
})->with([
    'modification Eloquent' => [fn () => PurgeJournalAudit::sole()->update(['nombre_entrees' => 999]), JournalAuditImmuableException::class],
    'suppression Eloquent' => [fn () => PurgeJournalAudit::sole()->delete(), JournalAuditImmuableException::class],
    'suppression SQL' => [fn () => DB::table('purges_journal_audit')->delete(), QueryException::class],
    'modification SQL' => [fn () => DB::table('purges_journal_audit')->update(['motif' => 'x']), QueryException::class],
]);

describe('commande journal:purger', function () {
    it('purges automatically according to the retention period', function () {
        entreeAuditIlYa(15);
        entreeAuditIlYa(13);

        $this->artisan('journal:purger')->assertSuccessful();

        expect(JournalAudit::count())->toBe(1)
            ->and(PurgeJournalAudit::sole()->type)->toBe(TypePurge::Automatique);
    });

    it('purges manually up to a date with a reason', function () {
        entreeAuditIlYa(5);

        $this->artisan('journal:purger', ['--avant' => now()->toDateString(), '--motif' => 'Demande direction'])
            ->assertSuccessful();

        expect(JournalAudit::count())->toBe(0)
            ->and(PurgeJournalAudit::sole()->motif)->toBe('Demande direction');
    });

    it('refuses a manual purge without a reason', function () {
        entreeAuditIlYa(5);

        $this->artisan('journal:purger', ['--avant' => now()->toDateString()])->assertFailed();

        expect(JournalAudit::count())->toBe(1);
    });

    it('is scheduled every day', function () {
        $evenement = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'journal:purger'));

        expect($evenement)->not->toBeNull()
            ->and($evenement->expression)->toBe('0 2 * * *');
    });
});

it('grants the manual purge to no role but the superadmin', function (Role $role, bool $autorise) {
    expect(utilisateurAvecRole($role)->can('purger-journal-audit'))->toBe($autorise);
})->with([
    [Role::Superadmin, true],
    [Role::Admin, false],
    [Role::Agent, false],
    [Role::Partenaire, false],
]);
