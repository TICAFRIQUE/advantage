<?php

use App\Services\IndexAveugle;

it('produces the same fingerprint for differently formatted values', function () {
    expect(IndexAveugle::calculer('ci 0012-345.678'))->toBe(IndexAveugle::calculer('CI0012345678'));
});

it('produces different fingerprints for different values', function () {
    expect(IndexAveugle::calculer('CI0000000001'))->not->toBe(IndexAveugle::calculer('CI0000000002'));
});

it('depends on the dedicated key and not only on the value', function () {
    $avant = IndexAveugle::calculer('CI0012345678');

    config(['plateforme.cle_hmac' => 'base64:'.base64_encode(random_bytes(32))]);

    expect(IndexAveugle::calculer('CI0012345678'))->not->toBe($avant);
});

it('refuses to compute a fingerprint without a configured key', function () {
    config(['plateforme.cle_hmac' => null]);

    IndexAveugle::calculer('CI0012345678');
})->throws(RuntimeException::class);
