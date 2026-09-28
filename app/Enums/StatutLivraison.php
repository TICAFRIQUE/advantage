<?php

namespace App\Enums;

/**
 * Statut de livraison d'une alerte.
 */
enum StatutLivraison: string
{
    case EnAttente = 'en_attente';
    case Envoyee = 'envoyee';
    case Echec = 'echec';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Envoyee => 'Envoyée',
            self::Echec => 'Échec',
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
