<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Compteur de sondes par IP et blocage temporaire (ProtectionRobots).
 * Un visiteur légitime ne demande jamais .env ni wp-login.php : le seuil
 * (10 sondes en 10 minutes par défaut) ne touche que les scanners.
 */
class Robots
{
    public static function estBloquee(string $ip): bool
    {
        return Cache::has(self::cleBlocage($ip));
    }

    public static function signalerSonde(string $ip): void
    {
        $cle = self::cleSondes($ip);
        RateLimiter::hit($cle, (int) config('plateforme.robots.fenetre_sondes_minutes') * 60);

        if (RateLimiter::attempts($cle) < (int) config('plateforme.robots.sondes_avant_blocage')) {
            return;
        }

        $minutes = (int) config('plateforme.robots.duree_blocage_minutes');
        Cache::put(self::cleBlocage($ip), true, now()->addMinutes($minutes));
        RateLimiter::clear($cle);

        Log::warning('Robot bloqué : trop de sondes depuis cette adresse IP.', ['ip' => $ip, 'minutes' => $minutes]);
    }

    public static function debloquer(string $ip): bool
    {
        RateLimiter::clear(self::cleSondes($ip));

        return Cache::forget(self::cleBlocage($ip));
    }

    private static function cleSondes(string $ip): string
    {
        return 'robots:sondes:'.$ip;
    }

    private static function cleBlocage(string $ip): string
    {
        return 'robots:bloquee:'.$ip;
    }
}
