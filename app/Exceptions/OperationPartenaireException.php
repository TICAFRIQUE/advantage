<?php

namespace App\Exceptions;

use DomainException;

/**
 * Refus métier dans le parcours partenaire (message destiné à l'opérateur).
 * Ne révèle jamais d'information sur le titulaire.
 */
class OperationPartenaireException extends DomainException
{
    public static function carteNonValide(): self
    {
        return new self('Carte non valide.');
    }

    public static function tropDeCodes(int $secondes): self
    {
        $minutes = max(1, (int) ceil($secondes / 60));

        return new self("Trop de codes demandés pour cette carte. Réessayez dans {$minutes} minute(s).");
    }

    public static function codeNonValide(): self
    {
        return new self("Ce code n'est plus valide. Demandez un nouveau code.");
    }

    public static function codeExpire(): self
    {
        return new self('Ce code a expiré. Demandez un nouveau code.');
    }

    public static function codeIncorrect(int $essaisRestants): self
    {
        return $essaisRestants > 0
            ? new self("Code incorrect. Il reste {$essaisRestants} essai(s).")
            : new self('Code incorrect. Trop d\'essais : ce code est bloqué, demandez-en un nouveau.');
    }
}
