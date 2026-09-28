<?php

namespace App\Enums;

/**
 * Opérations tracées dans l'historique permanent des cartes (`operations_cartes`).
 * Les passages chez les partenaires ont leur propre rapport (transactions).
 */
enum TypeOperationCarte: string
{
    case Activation = 'activation';
    case Suspension = 'suspension';
    case Reactivation = 'reactivation';
    case Revocation = 'revocation';
    case Expiration = 'expiration';
    case ModificationTitulaire = 'modification_titulaire';
    case ChangementTelephone = 'changement_telephone';

    public function libelle(): string
    {
        return match ($this) {
            self::Activation => 'Activation',
            self::Suspension => 'Suspension',
            self::Reactivation => 'Réactivation',
            self::Revocation => 'Révocation',
            self::Expiration => 'Expiration',
            self::ModificationTitulaire => 'Modification du titulaire',
            self::ChangementTelephone => 'Changement de téléphone',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Activation => 'bi-credit-card-2-front',
            self::Suspension => 'bi-pause-circle',
            self::Reactivation => 'bi-play-circle',
            self::Revocation => 'bi-x-octagon',
            self::Expiration => 'bi-calendar-x',
            self::ModificationTitulaire => 'bi-person-gear',
            self::ChangementTelephone => 'bi-phone',
        };
    }

    /**
     * Opération correspondant à un changement de statut, null si aucune.
     */
    public static function depuisChangementStatut(?StatutCarte $avant, StatutCarte $apres): ?self
    {
        return match ($apres) {
            StatutCarte::Suspendue => self::Suspension,
            StatutCarte::Revoquee => self::Revocation,
            StatutCarte::Expiree => self::Expiration,
            StatutCarte::Active => $avant === StatutCarte::Suspendue ? self::Reactivation : null,
            default => null,
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
