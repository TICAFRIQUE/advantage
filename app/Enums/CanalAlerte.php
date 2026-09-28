<?php

namespace App\Enums;

/**
 * Canal d'envoi d'une alerte d'expiration.
 */
enum CanalAlerte: string
{
    case Sms = 'sms';
    case InApp = 'in_app';

    /**
     * Libellé lisible pour l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::InApp => "Dans l'application",
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
