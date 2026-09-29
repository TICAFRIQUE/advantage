<?php

use App\Services\Sms\TexteSms;

it('replaces characters outside the GSM alphabet by their closest equivalent', function (string $texte, string $attendu) {
    expect(TexteSms::normaliser($texte))->toBe($attendu);
})->with([
    'accents hors GSM' => ['Hôtel Pâtisserie Brûlée Maître', 'Hotel Patisserie Brulée Maitre'],
    'accents GSM conservés' => ['élève à où Ça', 'élève à où Ça'],
    'ponctuation typographique' => ['l’offre « spéciale » — 10 %…', "l'offre \" spéciale \" - 10 %..."],
    'cédille minuscule' => ['reçu', 'recu'],
    'ligature' => ['cœur', 'coeur'],
    'espace insécable' => ["10\u{00A0}%", '10 %'],
    'caractère sans équivalent' => ['OK ✓', 'OK ?'],
]);

it('counts billed segments, extension characters counting double', function (string $texte, int $segments) {
    expect(TexteSms::segments($texte))->toBe($segments);
})->with([
    '160 caractères' => [str_repeat('a', 160), 1],
    '161 caractères' => [str_repeat('a', 161), 2],
    '306 caractères' => [str_repeat('a', 306), 2],
    '307 caractères' => [str_repeat('a', 307), 3],
    'extension' => [str_repeat('a', 159).'€', 2],
]);
