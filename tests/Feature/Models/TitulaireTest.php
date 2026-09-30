<?php

use App\Models\Titulaire;
use Illuminate\Support\Facades\Schema;

it('no longer stores any identity document', function () {
    expect(Schema::hasColumns('titulaires', ['numero_piece_identite']))->toBeFalse()
        ->and(Schema::hasColumns('titulaires', ['numero_piece_identite_hash']))->toBeFalse();
});

it('keeps every card of a holder for renewal history', function () {
    $titulaire = Titulaire::factory()->hasCartes(2)->create();

    expect($titulaire->cartes)->toHaveCount(2);
});
