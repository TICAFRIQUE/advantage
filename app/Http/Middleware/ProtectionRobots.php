<?php

namespace App\Http\Middleware;

use App\Services\Robots;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premier filtre de chaque requête, avant la session et la base de données :
 * - IP bloquée (trop de sondes récentes) : 403 immédiat ;
 * - outil de scan connu ou navigateur sans User-Agent : 403, compté comme sonde ;
 * - chemin typique des scanners (.env, .git, wp-login.php, *.php…) : 404 nu,
 *   compté comme sonde (aucune page ni session créée) ;
 * - toute réponse : ni X-Powered-By ni indexation par les moteurs de recherche.
 */
class ProtectionRobots
{
    /**
     * Chemins que l'application ne sert jamais et que les scanners sondent.
     *
     * @var list<string>
     */
    private const MOTIFS_SONDES = [
        '#(^|/)\.(?!well-known(/|$))#',
        '#\.(php\d?|phtml|phar|asp|aspx|jsp|cgi)$#i',
        '#(^|/)(wp-[a-z]|wordpress|xmlrpc|phpmyadmin|myadmin|pma(/|$)|cgi-bin|vendor/|boaform|actuator|telescope|_ignition)#i',
    ];

    /**
     * Outils de scan automatisés (un navigateur ne s'annonce jamais ainsi).
     *
     * @var list<string>
     */
    private const OUTILS_SCAN = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'wpscan', 'acunetix', 'nuclei', 'dirbuster',
        'gobuster', 'ffuf', 'feroxbuster', 'nessus', 'openvas', 'w3af', 'havij', 'jorgee', 'censysinspect',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        if (Robots::estBloquee($ip)) {
            return $this->reponse('Accès temporairement refusé.', 403);
        }

        if (! $request->is('up') && $this->estUnOutilDeScan($request)) {
            Robots::signalerSonde($ip);

            return $this->reponse('Accès refusé.', 403);
        }

        if ($this->estUneSonde(rawurldecode($request->path()))) {
            Robots::signalerSonde($ip);

            return $this->reponse('Page introuvable.', 404);
        }

        return $this->proteger($next($request));
    }

    private function estUneSonde(string $chemin): bool
    {
        foreach (self::MOTIFS_SONDES as $motif) {
            if (preg_match($motif, $chemin) === 1) {
                return true;
            }
        }

        return false;
    }

    private function estUnOutilDeScan(Request $request): bool
    {
        $navigateur = strtolower(trim((string) $request->userAgent()));

        return $navigateur === '' || Str::contains($navigateur, ['${', ...self::OUTILS_SCAN]);
    }

    private function reponse(string $texte, int $statut): Response
    {
        return $this->proteger(response($texte, $statut, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']));
    }

    private function proteger(Response $reponse): Response
    {
        // Version de PHP ajoutée par le moteur (expose_php) : jamais divulguée.
        header_remove('X-Powered-By');
        $reponse->headers->remove('X-Powered-By');

        // Application privée : aucune page indexée, archivée ni suivie.
        $reponse->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $reponse;
    }
}
