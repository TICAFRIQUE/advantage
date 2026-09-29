<?php

namespace App\Services;

use App\Enums\StatutCarte;
use App\Models\Carte;
use Illuminate\Support\Facades\Cache;

/**
 * Cartes actives arrivant à échéance (paliers des alertes : 1, 2, 3 mois),
 * pour le tableau de bord et la cloche de l'en-tête (affichée sur chaque
 * page). Résultat en cache 10 minutes, invalidé par CarteObserver à chaque
 * activation ou changement de carte.
 */
class EcheancesCartes
{
    public const CLE_CACHE = 'echeances-cartes';

    /**
     * @return array{paliers: array{1: int, 2: int, 3: int}, prochaines: list<array{id: int, numero: string, titulaire: string, expire_le: string, libelle: string, niveau: string}>}
     */
    public static function resume(): array
    {
        return Cache::remember(self::CLE_CACHE, now()->addMinutes(10), function (): array {
            $actives = fn () => Carte::query()->where('statut', StatutCarte::Active)->where('expire_le', '>', now());

            $resultat = $actives()
                ->selectRaw('SUM(expire_le <= ?) AS un_mois', [now()->addMonth()])
                ->selectRaw('SUM(expire_le <= ?) AS deux_mois', [now()->addMonths(2)])
                ->selectRaw('SUM(expire_le <= ?) AS trois_mois', [now()->addMonths(3)])
                ->first();

            // Données simples (pas de modèles) : sérialisables dans le cache.
            $prochaines = $actives()->with('titulaire')->where('expire_le', '<=', now()->addMonths(3))
                ->orderBy('expire_le')->limit(5)->get()
                ->map(fn (Carte $carte) => [
                    'id' => $carte->id,
                    'numero' => $carte->numeroFormate(),
                    'titulaire' => $carte->titulaire->nomComplet(),
                    'expire_le' => $carte->expire_le->format('d/m/Y'),
                    'libelle' => $carte->echeanceProche()['libelle'] ?? '',
                    'niveau' => $carte->echeanceProche()['niveau'] ?? 'info',
                ])->all();

            return [
                'paliers' => [1 => (int) $resultat->un_mois, 2 => (int) $resultat->deux_mois, 3 => (int) $resultat->trois_mois],
                'prochaines' => $prochaines,
            ];
        });
    }

    public static function oublier(): void
    {
        Cache::forget(self::CLE_CACHE);
    }
}
