<?php

namespace App\Enums;

/**
 * Statut d'une demande de code à usage unique.
 */
enum StatutDemandeOtp: string
{
    case EnAttente = 'en_attente';
    case Utilisee = 'utilisee';
    case Expiree = 'expiree';
    case Bloquee = 'bloquee';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Utilisee => 'Utilisée',
            self::Expiree => 'Expirée',
            self::Bloquee => 'Bloquée',
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
