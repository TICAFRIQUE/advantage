<?php

use App\Services\GenerateurPin;

it('generates five digit pins', function () {
    foreach (range(1, 200) as $i) {
        expect(GenerateurPin::generer())->toMatch('/^\d{5}$/');
    }
});

it('flags trivial pins', function (string $pin) {
    expect(GenerateurPin::estTrivial($pin))->toBeTrue();
})->with(['11111', '00000', '12345', '56789', '54321', '43210']);

it('accepts non trivial pins', function (string $pin) {
    expect(GenerateurPin::estTrivial($pin))->toBeFalse();
})->with(['48157', '12346', '11112', '90123', '02468']);
