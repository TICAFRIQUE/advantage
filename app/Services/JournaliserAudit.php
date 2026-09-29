<?php

namespace App\Services;

use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Point d'entrée unique d'écriture dans le journal d'audit (append-only).
 * Les champs sensibles sont retirés des données avant enregistrement.
 */
class JournaliserAudit
{
    /**
     * Champs jamais journalisés, même si un appelant les transmet.
     *
     * @var list<string>
     */
    public const CHAMPS_SENSIBLES = [
        'password',
        'pin',
        'remember_token',
        'code',
        'code_hash',
        'numero_piece_identite',
        'numero_piece_identite_hash',
    ];

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function enregistrer(
        string $action,
        ?Model $entite = null,
        array $donnees = [],
        ?User $acteur = null,
    ): JournalAudit {
        $acteur ??= Auth::user();

        return JournalAudit::create([
            'acteur_id' => $acteur?->getKey(),
            'type_acteur' => $acteur ? 'utilisateur' : (app()->runningInConsole() ? 'systeme' : 'anonyme'),
            'action' => $action,
            'type_entite' => $entite ? class_basename($entite) : null,
            'entite_id' => $entite?->getKey(),
            'donnees' => self::nettoyer($donnees) ?: null,
            'adresse_ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Navigateur et système, abrégés (« Chrome 140 · Windows »), pour les
     * connexions : aide à repérer un accès inhabituel sans conserver
     * l'empreinte complète du navigateur.
     */
    public static function navigateur(): ?string
    {
        $agent = (string) request()->userAgent();

        if ($agent === '') {
            return null;
        }

        $navigateur = match (true) {
            (bool) preg_match('/Edg\/(\d+)/', $agent, $m) => 'Edge '.$m[1],
            (bool) preg_match('/OPR\/(\d+)/', $agent, $m) => 'Opera '.$m[1],
            (bool) preg_match('/Chrome\/(\d+)/', $agent, $m) => 'Chrome '.$m[1],
            (bool) preg_match('/Firefox\/(\d+)/', $agent, $m) => 'Firefox '.$m[1],
            (bool) preg_match('/Version\/(\d+).*Safari/', $agent, $m) => 'Safari '.$m[1],
            default => 'Autre navigateur',
        };

        $systeme = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $systeme ? "{$navigateur} · {$systeme}" : $navigateur;
    }

    /**
     * Retire récursivement les champs sensibles.
     *
     * @param  array<mixed>  $donnees
     * @return array<mixed>
     */
    public static function nettoyer(array $donnees): array
    {
        $propres = [];

        foreach ($donnees as $cle => $valeur) {
            if (is_string($cle) && in_array($cle, self::CHAMPS_SENSIBLES, true)) {
                continue;
            }

            $propres[$cle] = is_array($valeur) ? self::nettoyer($valeur) : $valeur;
        }

        return $propres;
    }
}
