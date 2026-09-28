<?php

namespace App\Enums;

/**
 * Nature d'un SMS : détermine notamment si son contenu doit être masqué
 * après l'envoi (un code OTP n'est jamais conservé lisible).
 */
enum TypeSms: string
{
    case Otp = 'otp';
    case AlerteExpiration = 'alerte_expiration';
    case Information = 'information';

    public function libelle(): string
    {
        return match ($this) {
            self::Otp => 'Code de validation',
            self::AlerteExpiration => "Alerte d'expiration",
            self::Information => 'Information',
        };
    }

    public function contenuSensible(): bool
    {
        return $this === self::Otp;
    }
}
