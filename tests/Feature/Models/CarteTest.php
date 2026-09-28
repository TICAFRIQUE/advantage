<?php

use App\Enums\StatutCarte;
use App\Models\Carte;
use Illuminate\Database\UniqueConstraintViolationException;

it('derives the expiry date from the activation date plus twelve months', function () {
    $this->freezeTime();

    $carte = Carte::factory()->create(['active_le' => now()->setDate(2026, 3, 15)]);

    expect($carte->fresh()->expire_le->toDateString())->toBe('2027-03-15');
});

it('does not overflow into the next month when activated on the 29th of february', function () {
    $carte = Carte::factory()->create(['active_le' => now()->setDate(2028, 2, 29)]);

    expect($carte->fresh()->expire_le->toDateString())->toBe('2029-02-28');
});

it('overwrites a manually set expiry date with the derived one', function () {
    $carte = Carte::factory()->create(['active_le' => now()->setDate(2026, 1, 10)]);

    $carte->expire_le = now()->addYears(5);
    $carte->save();

    expect($carte->fresh()->expire_le->toDateString())->toBe('2027-01-10');
});

it('rejects a second card with an already used number', function () {
    Carte::factory()->create(['numero_carte' => '0000001']);

    Carte::factory()->create(['numero_carte' => '0000001']);
})->throws(UniqueConstraintViolationException::class);

it('rejects reusing the number of a soft deleted card', function () {
    Carte::factory()->create(['numero_carte' => '0000002'])->delete();

    Carte::factory()->create(['numero_carte' => '0000002']);
})->throws(UniqueConstraintViolationException::class);

it('is usable only when active and not yet past its expiry date', function (Closure $fabriquer, bool $utilisable) {
    expect($fabriquer()->estUtilisable())->toBe($utilisable);
})->with([
    'active et dans sa validité' => [fn () => Carte::factory()->create(), true],
    'active mais date échue (job pas encore passé)' => [fn () => Carte::factory()->activeeIlYa(12, 1)->create(), false],
    'suspendue' => [fn () => Carte::factory()->suspendue()->create(), false],
    'révoquée' => [fn () => Carte::factory()->revoquee()->create(), false],
    'expirée' => [fn () => Carte::factory()->expiree()->create(), false],
]);

it('formats the number in groups like the physical card', function () {
    $carte = Carte::factory()->make(['numero_carte' => '0000001']);

    expect($carte->numeroFormate())->toBe('000 000 1');
});

test('expired and revoked statuses are final', function (StatutCarte $statut, bool $definitif) {
    expect($statut->estDefinitif())->toBe($definitif);
})->with([
    'active' => [StatutCarte::Active, false],
    'suspendue' => [StatutCarte::Suspendue, false],
    'expiree' => [StatutCarte::Expiree, true],
    'revoquee' => [StatutCarte::Revoquee, true],
]);
