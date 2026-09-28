<?php

namespace App\Services\Sms;

/**
 * Réponse d'un fournisseur SMS à une demande d'envoi.
 */
final readonly class ResultatEnvoiSms
{
    private function __construct(
        public bool $succes,
        public ?string $reference = null,
        public ?string $erreur = null,
    ) {}

    public static function reussi(string $reference): self
    {
        return new self(true, reference: $reference);
    }

    public static function echoue(string $erreur): self
    {
        return new self(false, erreur: $erreur);
    }
}
