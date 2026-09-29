<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes\GestionSauvegardes;
use App\Services\Sauvegardes\SauvegardeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Contrôle de la configuration de production (à lancer après chaque
 * déploiement) : aucun secret n'est affiché, seulement le constat et la
 * correction attendue. Code de sortie 1 si un point est à corriger.
 */
#[Signature('securite:verifier')]
#[Description('Vérifie la configuration de sécurité de production (debug, clés, cookies, SMS, sauvegardes…)')]
class VerifierSecuriteCommande extends Command
{
    public function handle(GestionSauvegardes $sauvegardes): int
    {
        $problemes = 0;

        foreach ($this->controles($sauvegardes) as $libelle => [$conforme, $correction]) {
            if ($conforme) {
                $this->components->twoColumnDetail($libelle, '<fg=green;options=bold>OK</>');

                continue;
            }

            $problemes++;
            $this->components->twoColumnDetail($libelle, '<fg=red;options=bold>À CORRIGER</>');
            $this->line("  <fg=gray>→ {$correction}</>");
        }

        $this->newLine();

        if ($problemes > 0) {
            $this->components->error("{$problemes} point(s) à corriger.");

            return self::FAILURE;
        }

        $this->components->info('Configuration de production conforme.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{0: bool, 1: string}>
     */
    private function controles(GestionSauvegardes $sauvegardes): array
    {
        return [
            'Environnement de production' => [
                app()->isProduction(),
                'APP_ENV=production dans .env.',
            ],
            'Mode débogage désactivé' => [
                ! config('app.debug') && ! config('plateforme.securite.debug_neutralise'),
                'APP_DEBUG=false dans .env (il est neutralisé automatiquement en production, mais doit être corrigé).',
            ],
            'Clé de chiffrement (APP_KEY)' => [
                filled(config('app.key')),
                'php artisan key:generate (une seule fois, puis la copier hors du serveur).',
            ],
            'Clé HMAC de la plateforme' => [
                strlen((string) config('plateforme.cle_hmac')) >= 32,
                'PLATEFORME_CLE_HMAC : 32 octets aléatoires en base64, jamais modifiée ensuite.',
            ],
            'Adresse en HTTPS (APP_URL)' => [
                str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL=https://votre-domaine.ci',
            ],
            'Cookie de session sécurisé' => [
                config('session.secure') === true && config('session.http_only') === true,
                'SESSION_SECURE_COOKIE=true (cookie envoyé uniquement en HTTPS).',
            ],
            'Session chiffrée' => [
                config('session.encrypt') === true,
                'SESSION_ENCRYPT=true',
            ],
            'Journal sans données de débogage' => [
                ! in_array('debug', [config('logging.channels.single.level'), config('logging.channels.daily.level')], true),
                'LOG_LEVEL=warning',
            ],
            'Journal quotidien (rotation)' => [
                in_array('daily', (array) config('logging.channels.stack.channels'), true) || config('logging.default') === 'daily',
                'LOG_STACK=daily (fichiers conservés LOG_DAILY_DAYS jours, 14 par défaut).',
            ],
            'Fournisseur SMS réel configuré' => [
                config('plateforme.sms.driver') === 'ticafrique'
                    && filled(config('services.ticafrique.cle'))
                    && str_starts_with((string) config('services.ticafrique.url'), 'https://'),
                'SMS_DRIVER=ticafrique, TICAFRIQUE_SMS_API_KEY et une URL en HTTPS.',
            ],
            'Dossier des sauvegardes sûr' => [
                $this->dossierSauvegardesSur($sauvegardes),
                'Dossier absolu, hors de public/, accessible en écriture (Administration › Paramètres ou SAUVEGARDES_DOSSIER).',
            ],
            'Sauvegardes épargnées par le déploiement' => [
                $this->sauvegardesHorsDuDeploiement($sauvegardes->dossier()),
                'Dossier des sauvegardes hors du projet (ou dans storage/) : le déploiement (rsync --delete) effacerait tout autre dossier du projet.',
            ],
            'Fichiers CSS / JS construits' => [
                is_file(public_path('build/manifest.json')),
                'npm ci && npm run build (ou envoyer public/build).',
            ],
            'Aucun serveur de développement Vite' => [
                ! is_file(public_path('hot')),
                'Supprimer le fichier public/hot (les pages chargeraient leurs scripts depuis un serveur de développement).',
            ],
            'Fichiers privés non servis par HTTP' => [
                ! config('filesystems.disks.local.serve'),
                "'serve' => false pour le disque local (config/filesystems.php).",
            ],
            'Fichier .env protégé' => [
                $this->envProtege(),
                'chmod 600 .env (lisible par le seul compte du site).',
            ],
        ];
    }

    private function dossierSauvegardesSur(GestionSauvegardes $sauvegardes): bool
    {
        try {
            GestionSauvegardes::verifierDossier($sauvegardes->dossier());

            return true;
        } catch (SauvegardeException) {
            return false;
        }
    }

    private function sauvegardesHorsDuDeploiement(string $dossier): bool
    {
        $normaliser = fn (string $chemin) => mb_strtolower(rtrim(str_replace('\\', '/', $chemin), '/')).'/';
        $dossier = $normaliser($dossier);

        return ! str_starts_with($dossier, $normaliser(base_path()))
            || str_starts_with($dossier, $normaliser(storage_path()));
    }

    private function envProtege(): bool
    {
        $fichier = app()->environmentFilePath();

        // Droits Unix uniquement (sans objet sous Windows) ; .env absent : variables du serveur.
        if (PHP_OS_FAMILY === 'Windows' || ! is_file($fichier)) {
            return true;
        }

        return (fileperms($fichier) & 0o077) === 0;
    }
}
