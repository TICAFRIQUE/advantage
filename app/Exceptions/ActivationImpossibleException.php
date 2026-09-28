<?php

namespace App\Exceptions;

use DomainException;

/**
 * Règle métier empêchant une activation (message destiné à l'agent).
 */
class ActivationImpossibleException extends DomainException
{
    public static function carteDejaActivee(string $numero): self
    {
        return new self("La carte {$numero} a déjà été activée. Un numéro de carte ne peut être utilisé qu'une seule fois.");
    }

    public static function titulaireDejaEquipe(string $numero): self
    {
        return new self("Ce titulaire possède déjà une carte active ({$numero}). Déclarez-la perdue avant d'en activer une nouvelle.");
    }
}
