<?php

use App\Actions\Partenaire\ValiderOtpAction;
use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Enums\StatutDemandeOtp;
use App\Exceptions\OperationPartenaireException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;
use Database\Factories\DemandeOtpFactory;

/**
 * @return array{0: DemandeOtp, 1: User}
 */
function demandeEnAttente(array $etat = []): array
{
    $operateur = utilisateurAvecRole(Role::Partenaire);
    $demande = DemandeOtp::factory()->create(array_merge([
        'partenaire_id' => $operateur->partenaire_id,
        'carte_id' => Carte::factory(),
    ], $etat));

    return [$demande, $operateur];
}

function valider(DemandeOtp $demande, User $operateur, string $code = DemandeOtpFactory::CODE): Transaction
{
    return app(ValiderOtpAction::class)($demande, $code, $operateur->partenaire->fresh(), $operateur);
}

it('creates the transaction at the partner rate and consumes the code', function () {
    [$demande, $operateur] = demandeEnAttente();
    $operateur->partenaire->update(['taux_reduction' => 15]);

    $transaction = valider($demande, $operateur);

    expect($transaction)
        ->carte_id->toBe($demande->carte_id)
        ->partenaire_id->toBe($operateur->partenaire_id)
        ->valide_par_id->toBe($operateur->id)
        ->taux_applique->toBe('15.00')
        ->and($demande->fresh())
        ->statut->toBe(StatutDemandeOtp::Utilisee)
        ->utilisee_le->not->toBeNull()
        ->and(JournalAudit::where('action', 'transaction.creee')->exists())->toBeTrue();
});

it('keeps the applied rate even if the partner rate changes later', function () {
    [$demande, $operateur] = demandeEnAttente();
    $operateur->partenaire->update(['taux_reduction' => 10]);
    $transaction = valider($demande, $operateur);

    $operateur->partenaire->update(['taux_reduction' => 25]);

    expect($transaction->fresh()->taux_applique)->toBe('10.00');
});

it('counts a wrong code and tells how many attempts remain', function () {
    [$demande, $operateur] = demandeEnAttente();

    expect(fn () => valider($demande, $operateur, '000000'))
        ->toThrow(OperationPartenaireException::class, 'Il reste 2 essai(s)')
        ->and($demande->fresh()->tentatives)->toBe(1);
});

it('blocks the code at the third wrong attempt, even for the right code afterwards', function () {
    [$demande, $operateur] = demandeEnAttente();

    foreach (range(1, 2) as $essai) {
        rescue(fn () => valider($demande, $operateur, '000000'), report: false);
    }

    expect(fn () => valider($demande, $operateur, '000000'))->toThrow(OperationPartenaireException::class, 'ce code est bloqué')
        ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Bloquee)
        ->and(fn () => valider($demande, $operateur))->toThrow(OperationPartenaireException::class, "n'est plus valide")
        ->and(Transaction::count())->toBe(0);
});

it('refuses an expired code and marks it expired', function () {
    [$demande, $operateur] = demandeEnAttente();
    $this->travel(6)->minutes();

    expect(fn () => valider($demande, $operateur))->toThrow(OperationPartenaireException::class, 'a expiré')
        ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Expiree);
});

it('returns the same transaction when the code is submitted twice', function () {
    [$demande, $operateur] = demandeEnAttente();

    $premiere = valider($demande, $operateur);
    $seconde = valider($demande, $operateur);

    expect($seconde->id)->toBe($premiere->id)
        ->and(Transaction::count())->toBe(1);
});

it('refuses the code if the card was revoked in the meantime', function () {
    [$demande, $operateur] = demandeEnAttente();
    Carte::whereKey($demande->carte_id)->update(['statut' => StatutCarte::Revoquee]);

    expect(fn () => valider($demande, $operateur))->toThrow(OperationPartenaireException::class, 'Carte non valide.')
        ->and(Transaction::count())->toBe(0);
});

it('refuses a code requested by another partner, even when policies are bypassed', function () {
    [$demande] = demandeEnAttente();
    $autre = utilisateurAvecRole(Role::Partenaire, ['partenaire_id' => Partenaire::factory()->create()->id]);

    expect(fn () => valider($demande, $autre))->toThrow(OperationPartenaireException::class, "n'est plus valide")
        ->and(Transaction::count())->toBe(0);
});
