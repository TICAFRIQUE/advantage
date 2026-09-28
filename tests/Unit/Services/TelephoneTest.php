<?php

use App\Services\Telephone;

describe("Côte d'Ivoire (pays par défaut)", function () {
    it('accepts any ten-digit number whatever its prefix', function (string $saisie, string $attendu) {
        expect(Telephone::normaliser($saisie))->toBe($attendu);
    })->with([
        'mobile 07' => ['0707123456', '+2250707123456'],
        'mobile 01' => ['01 07 12 34 56', '+2250107123456'],
        'mobile 05' => ['05-05-12-34-56', '+2250505123456'],
        'fixe 27' => ['2722123456', '+2252722123456'],
        'autre préfixe' => ['0207123456', '+2250207123456'],
    ]);

    it('accepts the country code written in any usual way', function (string $saisie) {
        expect(Telephone::normaliser($saisie))->toBe('+2250707123456');
    })->with([
        'avec +' => ['+225 07 07 12 34 56'],
        'avec 00' => ['002250707123456'],
        'sans +' => ['2250707123456'],
    ]);

    it('rejects anything that is not exactly ten digits', function (?string $saisie) {
        expect(Telephone::normaliser($saisie))->toBeNull();
    })->with([
        'neuf chiffres' => ['070712345'],
        'onze chiffres' => ['07071234567'],
        'lettres seules' => ['abcdefghij'],
        'vide' => [''],
        'null' => [null],
    ]);

    it('formats a stored number in pairs', function () {
        expect(Telephone::formater('+2250707123456'))->toBe('+225 07 07 12 34 56');
    });
});

describe('autres pays', function () {
    it('normalises a national number for the selected country', function (string $pays, string $saisie, string $attendu) {
        expect(Telephone::normaliser($saisie, $pays))->toBe($attendu);
    })->with([
        'Sénégal' => ['SN', '77 123 45 67', '+221771234567'],
        'Mali' => ['ML', '76 12 34 56', '+22376123456'],
        'Bénin (10 chiffres)' => ['BJ', '01 97 12 34 56', '+2290197123456'],
        'Ghana avec 0 national' => ['GH', '024 123 4567', '+233241234567'],
        'Ghana sans 0 national' => ['GH', '24 123 4567', '+233241234567'],
        'France avec 0 national' => ['FR', '06 12 34 56 78', '+33612345678'],
    ]);

    it('rejects a number with the wrong length for the selected country', function (string $pays, string $saisie) {
        expect(Telephone::normaliser($saisie, $pays))->toBeNull();
    })->with([
        'Sénégal à 10 chiffres' => ['SN', '7712345678'],
        'Mali à 9 chiffres' => ['ML', '761234567'],
    ]);

    it('detects the country from an international number regardless of the selection', function () {
        expect(Telephone::normaliser('+221 77 123 45 67', 'CI'))->toBe('+221771234567');
    });

    it('prefers the longest matching country code', function () {
        expect(Telephone::paysDepuisIndicatif('225070712345'))->toBe('CI');
    });

    it('rejects an international number from an unconfigured country', function () {
        expect(Telephone::normaliser('+1 202 555 0147'))->toBeNull();
    });

    it('falls back to the default country for an unknown country code', function () {
        expect(Telephone::normaliser('0707123456', 'ZZ'))->toBe('+2250707123456');
    });

    it('formats numbers with the grouping of their country', function (string $stocke, string $affiche) {
        expect(Telephone::formater($stocke))->toBe($affiche);
    })->with([
        ['+221771234567', '+221 77 123 45 67'],
        ['+233241234567', '+233 24 123 4567'],
        ['+33612345678', '+33 6 12 34 56 78'],
    ]);
});
