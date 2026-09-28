<?php

namespace App\Enums;

/**
 * Statut d'une transaction (passage validé chez un partenaire).
 */
enum StatutTransaction: string
{
    case Validee = 'validee';
    case Annulee = 'annulee';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
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
