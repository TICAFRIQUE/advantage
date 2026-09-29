<?php

use App\Actions\Maintenance\PurgerDonneesTechniques;
use App\Enums\StatutDemandeOtp;
use App\Enums\StatutLivraison;
use App\Enums\TypeOperationCarte;
use App\Enums\TypeSms;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use App\Models\OperationCarte;
use App\Models\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('rétention des données techniques', function () {
    it('deletes old finished codes except those proving a transaction', function () {
        $ancienne = DemandeOtp::factory()->create(['statut' => StatutDemandeOtp::Expiree, 'created_at' => now()->subDays(91)]);
        $recente = DemandeOtp::factory()->create(['statut' => StatutDemandeOtp::Expiree, 'created_at' => now()->subDays(10)]);
        $enAttente = DemandeOtp::factory()->create(['statut' => StatutDemandeOtp::EnAttente, 'created_at' => now()->subDays(91)]);
        $preuve = DemandeOtp::factory()->utilisee()->create(['created_at' => now()->subDays(200)]);
        Transaction::factory()->create(['demande_otp_id' => $preuve->id]);

        $this->artisan('donnees:purger')->expectsOutputToContain('Demandes de code supprimées')->assertSuccessful();

        expect(DemandeOtp::whereKey($ancienne->id)->exists())->toBeFalse()
            ->and(DemandeOtp::whereKey([$recente->id, $enAttente->id, $preuve->id])->count())->toBe(3)
            ->and(JournalAudit::where('action', 'donnees.purgees')->sole()->donnees)->toBe(['demandes_otp' => 1, 'messages_sms' => 0]);
    });

    it('deletes old sent or failed SMS, never pending ones', function () {
        $message = fn (StatutLivraison $statut, int $jours) => tap((new MessageSms)->forceFill([
            'telephone' => '+2250707123456', 'type' => TypeSms::Information, 'contenu' => 'Bonjour',
            'statut' => $statut, 'fournisseur' => 'simulation', 'created_at' => now()->subDays($jours),
        ]))->save();
        $envoye = $message(StatutLivraison::Envoyee, 91);
        $echec = $message(StatutLivraison::Echec, 120);
        $enAttente = $message(StatutLivraison::EnAttente, 120);
        $recent = $message(StatutLivraison::Envoyee, 5);

        $resultat = app(PurgerDonneesTechniques::class)();

        expect($resultat['messages_sms'])->toBe(2)
            ->and(MessageSms::pluck('id')->sort()->values()->all())->toBe(collect([$enAttente->id, $recent->id])->sort()->values()->all());
    });

    it('runs every night after the audit log purge', function () {
        $taches = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [$e->command => $e->expression]);

        expect($taches->first(fn ($x, $commande) => str_contains($commande, 'donnees:purger')))->toBe('30 2 * * *');
    });
});

describe('historique des cartes en ajout seul', function () {
    it('refuses any update or deletion, even in raw SQL', function (string $operation) {
        $operation = OperationCarte::enregistrer(Carte::factory()->create(), TypeOperationCarte::Activation, systeme: true);

        expect(fn () => $operation === 'update'
            ? DB::table('operations_cartes')->where('id', $operation->id)->update(['motif' => 'falsifié'])
            : DB::table('operations_cartes')->where('id', $operation->id)->delete()
        )->toThrow(QueryException::class, 'operations_cartes est en ajout seul');
    })->with(['update', 'delete']);
});
