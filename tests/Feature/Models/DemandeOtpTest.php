<?php

use App\Models\DemandeOtp;
use Illuminate\Support\Facades\DB;

it('stores only a hash of the one-time code', function () {
    $demande = DemandeOtp::factory()->create();

    $brut = DB::table('demandes_otp')->where('id', $demande->id)->value('code_hash');

    expect($brut)->not->toBe(DemandeOtp::factory()::CODE)
        ->and(password_verify(DemandeOtp::factory()::CODE, $brut))->toBeTrue();
});

it('does not expose the code hash when serialized', function () {
    expect(DemandeOtp::factory()->create()->toArray())->not->toHaveKey('code_hash');
});

it('reports itself expired once its expiry time has passed', function () {
    expect(DemandeOtp::factory()->create()->estExpiree())->toBeFalse()
        ->and(DemandeOtp::factory()->expiree()->create()->estExpiree())->toBeTrue();
});
