<?php

namespace App\Enums;

/**
 * Cycle de vie d'une carte. Une carte expirée ou révoquée n'est jamais réactivée.
 */
enum StatutCarte: string
{
    case NonActivee = 'non_activee';
    case Active = 'active';
    case Expiree = 'expiree';
    case Suspendue = 'suspendue';
    case Revoquee = 'revoquee';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::NonActivee => 'Non activée',
            self::Active => 'Active',
            self::Expiree => 'Expirée',
            self::Suspendue => 'Suspendue',
            self::Revoquee => 'Révoquée',
        };
    }

    /**
     * Statut définitif : la carte ne pourra plus jamais redevenir active.
     */
    public function estDefinitif(): bool
    {
        return in_array($this, [self::Expiree, self::Revoquee], true);
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
