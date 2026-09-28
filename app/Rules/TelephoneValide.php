<?php

namespace App\Rules;

use App\Services\Telephone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro valide pour le pays choisi (Côte d'Ivoire par défaut), ou numéro
 * international (+indicatif) d'un pays configuré.
 */
class TelephoneValide implements ValidationRule
{
    private ?string $pays;

    /**
     * Le pays provient de la saisie brute : toute valeur non textuelle
     * (ex. tableau injecté) est ignorée au profit du pays par défaut.
     */
    public function __construct(mixed $pays = null)
    {
        $this->pays = is_string($pays) ? $pays : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Telephone::normaliser($value, $this->pays) !== null) {
            return;
        }

        $pays = Telephone::tousLesPays()[strtoupper((string) $this->pays)] ?? Telephone::tousLesPays()[Telephone::paysParDefaut()];

        $fail("Le :attribute doit comporter {$pays['longueur']} chiffres ({$pays['nom']}, +{$pays['indicatif']}).");
    }
}
