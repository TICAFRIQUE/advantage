<?php

namespace App\Services;

use App\Models\Parametre;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres de l'application (identité, sauvegardes) : lus sur chaque page
 * (nom, logo), donc gardés en cache et invalidés à chaque enregistrement.
 * Repli sur les valeurs par défaut si la table n'existe pas encore
 * (installation, migrations en cours).
 */
class Parametres
{
    public const CLE_CACHE = 'parametres-application';

    /**
     * Logo par défaut (fourni avec l'application).
     */
    public const LOGO_DEFAUT = 'images/logo-fg.png';

    public static function get(string $cle, ?string $defaut = null): ?string
    {
        return self::tous()[$cle] ?? $defaut;
    }

    public static function definir(string $cle, ?string $valeur, ?User $auteur): void
    {
        Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => $valeur, 'modifie_par_id' => $auteur?->id]);
        Cache::forget(self::CLE_CACHE);
    }

    public static function nomApplication(): string
    {
        return self::get('identite.nom_application') ?: 'ADVANTAGE';
    }

    public static function nomOrganisation(): string
    {
        return self::get('identite.nom_organisation') ?: 'fontaine GROUP';
    }

    /**
     * Chemin du logo relatif au dossier public.
     */
    public static function logo(): string
    {
        $logo = self::get('identite.logo');

        return $logo !== null && is_file(public_path($logo)) ? $logo : self::LOGO_DEFAUT;
    }

    public static function logoUrl(): string
    {
        return asset(self::logo()).'?v='.substr(md5(self::logo()), 0, 8);
    }

    public static function logoPersonnalise(): bool
    {
        return self::logo() !== self::LOGO_DEFAUT;
    }

    /**
     * @return array<string, ?string>
     */
    private static function tous(): array
    {
        try {
            return Cache::rememberForever(self::CLE_CACHE, fn () => Parametre::query()->pluck('valeur', 'cle')->all());
        } catch (QueryException) {
            return [];
        }
    }
}
