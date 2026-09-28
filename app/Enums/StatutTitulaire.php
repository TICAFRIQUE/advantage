<?php

namespace App\Enums;

/**
 * Statut d'un titulaire de carte.
 */
enum StatutTitulaire: string
{
    case Actif = 'actif';
    case Inactif = 'inactif';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Inactif => 'Inactif',
        };
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
