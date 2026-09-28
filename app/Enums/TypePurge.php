<?php

namespace App\Enums;

/**
 * Origine d'une purge du journal d'audit.
 */
enum TypePurge: string
{
    case Automatique = 'automatique';
    case Manuelle = 'manuelle';

    public function libelle(): string
    {
        return match ($this) {
            self::Automatique => 'Automatique (rétention)',
            self::Manuelle => 'Manuelle',
        };
    }
}
