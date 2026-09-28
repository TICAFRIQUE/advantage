<?php

namespace App\Enums;

/**
 * Palier d'alerte avant expiration d'une carte.
 */
enum PalierAlerte: string
{
    case TroisMois = '3_mois';
    case DeuxMois = '2_mois';
    case UnMois = '1_mois';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::TroisMois => '3 mois',
            self::DeuxMois => '2 mois',
            self::UnMois => '1 mois',
        };
    }

    /**
     * Nombre de mois avant expiration correspondant au palier.
     */
    public function mois(): int
    {
        return match ($this) {
            self::TroisMois => 3,
            self::DeuxMois => 2,
            self::UnMois => 1,
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
