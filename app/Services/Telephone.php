<?php

namespace App\Services;

/**
 * Normalisation des numéros de téléphone selon le pays (config
 * plateforme.telephone). Format stocké : E.164, « +<indicatif><numéro> ».
 * La Côte d'Ivoire est le pays par défaut.
 */
class Telephone
{
    /**
     * Retourne le numéro au format E.164, ou null s'il est invalide.
     *
     * - saisie internationale (« +221… », « 00221… ») : le pays est déduit de l'indicatif ;
     * - saisie nationale : interprétée selon $pays (défaut : pays configuré).
     */
    public static function normaliser(?string $saisie, ?string $pays = null): ?string
    {
        $saisie = trim((string) $saisie);
        $chiffres = (string) preg_replace('/\D/', '', $saisie);

        if ($chiffres === '') {
            return null;
        }

        $international = str_starts_with($saisie, '+') || str_starts_with($chiffres, '00');

        if ($international) {
            $chiffres = str_starts_with($chiffres, '00') ? substr($chiffres, 2) : $chiffres;
            $code = self::paysDepuisIndicatif($chiffres);

            return $code === null ? null : self::valider($code, substr($chiffres, strlen(self::config($code)['indicatif'])));
        }

        $code = self::codePays($pays);
        $config = self::config($code);

        // Indicatif saisi sans « + » (ex. 2250707123456) : longueur sans ambiguïté.
        if (strlen($chiffres) === strlen($config['indicatif']) + $config['longueur'] && str_starts_with($chiffres, $config['indicatif'])) {
            return self::valider($code, substr($chiffres, strlen($config['indicatif'])));
        }

        return self::valider($code, $chiffres);
    }

    /**
     * Affichage lisible selon les groupes du pays : « +225 07 07 12 34 56 ».
     */
    public static function formater(string $telephone): string
    {
        $chiffres = ltrim($telephone, '+');
        $code = self::paysDepuisIndicatif($chiffres);

        if ($code === null) {
            return $telephone;
        }

        $config = self::config($code);
        $national = substr($chiffres, strlen($config['indicatif']));
        $morceaux = [];
        $position = 0;

        foreach ($config['groupes'] as $taille) {
            $morceaux[] = substr($national, $position, $taille);
            $position += $taille;
        }

        return '+'.$config['indicatif'].' '.trim(implode(' ', array_filter($morceaux, 'strlen')));
    }

    /**
     * Code ISO du pays d'un numéro E.164 (null si indicatif non configuré).
     */
    public static function paysDepuisIndicatif(string $chiffres): ?string
    {
        $chiffres = ltrim($chiffres, '+');

        // Les indicatifs les plus longs d'abord (évite qu'un « 22 » masque un « 225 »).
        $pays = collect(self::tousLesPays())->sortByDesc(fn (array $config) => strlen($config['indicatif']));

        foreach ($pays as $code => $config) {
            if (str_starts_with($chiffres, $config['indicatif'])) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Numéro partiellement masqué pour les traces : « +22507******93 ».
     */
    public static function masquer(string $telephone): string
    {
        return mb_substr($telephone, 0, 6).str_repeat('*', max(0, mb_strlen($telephone) - 8)).mb_substr($telephone, -2);
    }

    /**
     * @return array<string, array{nom: string, indicatif: string, longueur: int, prefixe_national: ?string, groupes: list<int>}>
     */
    public static function tousLesPays(): array
    {
        return config('plateforme.telephone.pays');
    }

    public static function paysParDefaut(): string
    {
        return (string) config('plateforme.telephone.pays_defaut', 'CI');
    }

    private static function codePays(?string $pays): string
    {
        $pays = strtoupper((string) $pays);

        return array_key_exists($pays, self::tousLesPays()) ? $pays : self::paysParDefaut();
    }

    /**
     * @return array{nom: string, indicatif: string, longueur: int, prefixe_national: ?string, groupes: list<int>}
     */
    private static function config(string $code): array
    {
        return self::tousLesPays()[$code];
    }

    private static function valider(string $code, string $national): ?string
    {
        $config = self::config($code);
        $prefixe = $config['prefixe_national'];

        if ($prefixe !== null && strlen($national) === $config['longueur'] + strlen($prefixe) && str_starts_with($national, $prefixe)) {
            $national = substr($national, strlen($prefixe));
        }

        if (strlen($national) !== $config['longueur']) {
            return null;
        }

        return '+'.$config['indicatif'].$national;
    }
}
