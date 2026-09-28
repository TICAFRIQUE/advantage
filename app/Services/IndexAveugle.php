<?php

namespace App\Services;

use RuntimeException;

/**
 * Calcule une empreinte HMAC déterministe d'une donnée chiffrée afin de
 * pouvoir la rechercher et en garantir l'unicité sans la stocker en clair.
 */
class IndexAveugle
{
    /**
     * Normalise puis hache une valeur (HMAC-SHA256, 64 caractères hexadécimaux).
     */
    public static function calculer(string $valeur): string
    {
        return hash_hmac('sha256', self::normaliser($valeur), self::cle());
    }

    /**
     * Supprime espaces, tirets et points, et met en majuscules, pour que
     * « ci 0012-345 » et « CI0012345 » produisent la même empreinte.
     */
    public static function normaliser(string $valeur): string
    {
        return mb_strtoupper((string) preg_replace('/[\s\-.]/u', '', $valeur));
    }

    /**
     * Clé HMAC dédiée, distincte de APP_KEY.
     */
    private static function cle(): string
    {
        $cle = config('plateforme.cle_hmac');

        if (blank($cle)) {
            throw new RuntimeException('La clé PLATEFORME_CLE_HMAC n\'est pas configurée.');
        }

        return str_starts_with($cle, 'base64:') ? base64_decode(substr($cle, 7)) : $cle;
    }
}
