<?php

use App\Models\DemandeOtp;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;

it('allows only one transaction per one-time code request', function () {
    $demande = DemandeOtp::factory()->utilisee()->create();

    Transaction::factory()->create(['demande_otp_id' => $demande->id]);
    Transaction::factory()->create(['demande_otp_id' => $demande->id]);
})->throws(UniqueConstraintViolationException::class);

it('takes the card and partner from its one-time code request', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->carte_id)->toBe($transaction->demandeOtp->carte_id)
        ->and($transaction->partenaire_id)->toBe($transaction->demandeOtp->partenaire_id);
});
