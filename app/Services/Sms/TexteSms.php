<?php

namespace App\Services\Sms;

use Illuminate\Support\Str;

/**
 * Texte d'un SMS limité à l'alphabet GSM 03.38 : un seul caractère hors de
 * cet alphabet (ô, ê, â, ’, «…) fait passer tout le message en Unicode,
 * limité à 70 caractères par segment au lieu de 160 — le SMS coûte alors
 * deux fois plus d'unités. Les caractères hors alphabet sont remplacés par
 * leur équivalent le plus proche (ô → o, « → ").
 */
final class TexteSms
{
    /**
     * Alphabet GSM de base (1 caractère = 1 septet).
     */
    private const BASE = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /**
     * Extension GSM (1 caractère = 2 septets).
     */
    private const EXTENSION = '^{}\\[~]|€';

    /**
     * @var array<string, string>
     */
    private const EQUIVALENTS = [
        '’' => "'", '‘' => "'", '`' => "'", '´' => "'",
        '“' => '"', '”' => '"', '«' => '"', '»' => '"',
        '–' => '-', '—' => '-', '…' => '...',
        "\u{00A0}" => ' ', "\u{202F}" => ' ', "\t" => ' ',
        'ç' => 'c', 'œ' => 'oe', 'Œ' => 'OE',
    ];

    public static function normaliser(string $texte): string
    {
        $resultat = '';

        foreach (mb_str_split($texte) as $caractere) {
            $resultat .= self::estGsm($caractere) ? $caractere : self::equivalent($caractere);
        }

        return $resultat;
    }

    /**
     * Nombre de segments facturés (texte supposé normalisé) : 160 septets pour
     * un SMS seul, 153 par segment au-delà (en-tête de concaténation).
     */
    public static function segments(string $texte): int
    {
        $septets = 0;

        foreach (mb_str_split($texte) as $caractere) {
            $septets += str_contains(self::EXTENSION, $caractere) ? 2 : 1;
        }

        return $septets <= 160 ? 1 : (int) ceil($septets / 153);
    }

    private static function estGsm(string $caractere): bool
    {
        return str_contains(self::BASE, $caractere) || str_contains(self::EXTENSION, $caractere);
    }

    private static function equivalent(string $caractere): string
    {
        $equivalent = self::EQUIVALENTS[$caractere] ?? Str::ascii($caractere);

        return collect(mb_str_split($equivalent))->every(self::estGsm(...)) && $equivalent !== '' ? $equivalent : '?';
    }
}
