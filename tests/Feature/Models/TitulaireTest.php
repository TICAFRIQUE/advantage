<?php

use App\Models\Titulaire;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('never stores the identity document number in clear text', function () {
    $titulaire = Titulaire::factory()->create(['numero_piece_identite' => 'CI0012345678']);

    $brut = DB::table('titulaires')->where('id', $titulaire->id)->first();

    expect($brut->numero_piece_identite)->not->toContain('CI0012345678')
        ->and(Crypt::decryptString($brut->numero_piece_identite))->toBe('CI0012345678')
        ->and($brut->numero_piece_identite_hash)->toHaveLength(64)
        ->and($brut->numero_piece_identite_hash)->not->toContain('CI0012345678');
});

it('finds a holder by identity document number regardless of formatting', function () {
    $titulaire = Titulaire::factory()->create(['numero_piece_identite' => 'CI0012345678']);

    expect(Titulaire::parNumeroPiece('ci 0012-345.678')->first()?->id)->toBe($titulaire->id);
});

it('updates the search fingerprint when the identity document number changes', function () {
    $titulaire = Titulaire::factory()->create(['numero_piece_identite' => 'CI0000000001']);

    $titulaire->update(['numero_piece_identite' => 'CI0000000002']);

    expect(Titulaire::parNumeroPiece('CI0000000001')->exists())->toBeFalse()
        ->and(Titulaire::parNumeroPiece('CI0000000002')->first()?->id)->toBe($titulaire->id);
});

it('rejects two holders with the same identity document', function () {
    Titulaire::factory()->create(['numero_piece_identite' => 'CI0012345678']);

    Titulaire::factory()->create(['numero_piece_identite' => 'ci-0012345678']);
})->throws(UniqueConstraintViolationException::class);

it('does not expose the identity document when serialized', function () {
    $titulaire = Titulaire::factory()->create();

    expect($titulaire->toArray())
        ->not->toHaveKey('numero_piece_identite')
        ->not->toHaveKey('numero_piece_identite_hash');
});

it('keeps every card of a holder for renewal history', function () {
    $titulaire = Titulaire::factory()->hasCartes(2)->create();

    expect($titulaire->cartes)->toHaveCount(2);
});
