<?php

namespace App\Services;

/**
 * Génère les PIN de connexion (5 chiffres) avec un aléa cryptographique,
 * en excluant les combinaisons triviales.
 */
class GenerateurPin
{
    public static function generer(): string
    {
        $longueur = (int) config('plateforme.connexion.longueur_pin', 5);

        do {
            $pin = str_pad((string) random_int(0, 10 ** $longueur - 1), $longueur, '0', STR_PAD_LEFT);
        } while (self::estTrivial($pin));

        return $pin;
    }

    /**
     * Trivial : un seul chiffre répété (11111) ou suite croissante/décroissante (12345, 54321).
     */
    public static function estTrivial(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        $croissant = $decroissant = true;

        for ($i = 1; $i < strlen($pin); $i++) {
            $ecart = (int) $pin[$i] - (int) $pin[$i - 1];
            $croissant = $croissant && $ecart === 1;
            $decroissant = $decroissant && $ecart === -1;
        }

        return $croissant || $decroissant;
    }
}
