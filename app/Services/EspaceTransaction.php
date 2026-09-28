<?php

namespace App\Services;

/**
 * Le parcours de transaction est servi par les mêmes contrôleurs et vues dans
 * deux espaces : « gestion.transaction.* » (back-office, pour le compte d'un
 * partenaire) et « partenaire.transaction.* » (opérateur). Ce service génère
 * les routes de l'espace en cours.
 */
class EspaceTransaction
{
    public static function prefixe(): string
    {
        return request()->routeIs('gestion.*') ? 'gestion.transaction.' : 'partenaire.transaction.';
    }

    public static function estGestion(): bool
    {
        return request()->routeIs('gestion.*');
    }

    /**
     * @param  mixed  $parametres
     */
    public static function route(string $nom, $parametres = []): string
    {
        return route(self::prefixe().$nom, $parametres);
    }
}
