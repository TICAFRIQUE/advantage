<?php

namespace App\Services\Sauvegardes;

use App\Models\User;
use App\Services\EcheancesCartes;
use App\Services\JournaliserAudit;
use App\Services\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sauvegardes de la base de données : fichiers « advantage-AAAA-MM-JJ-HHMMSS.sql.gz »
 * dans un dossier paramétrable (jamais dans le dossier public), rotation des
 * plus anciennes au-delà de la limite (10 par défaut), restauration précédée
 * d'une sauvegarde de sécurité.
 */
class GestionSauvegardes
{
    /**
     * Seuls les fichiers produits par l'application sont lus, téléchargés,
     * restaurés ou supprimés (aucun chemin arbitraire).
     */
    private const MOTIF_FICHIER = '/^advantage-\d{4}-\d{2}-\d{2}-\d{6}(-avant-restauration)?\.sql\.gz$/';

    public function __construct(private MoteurSauvegarde $moteur) {}

    public function dossier(): string
    {
        return rtrim((string) (Parametres::get('sauvegardes.dossier') ?: config('plateforme.sauvegardes.dossier')), '\\/');
    }

    public static function dossierParDefaut(): string
    {
        return str_replace('\\', '/', storage_path('app/sauvegardes'));
    }

    /**
     * Dossier choisi : absolu, hors du dossier public, épargné par les
     * déploiements (hors du projet ou dans storage/app/), créé s'il n'existe
     * pas et accessible en écriture.
     *
     * @throws SauvegardeException
     */
    public static function verifierDossier(string $dossier): string
    {
        $dossier = rtrim(str_replace('\\', '/', trim($dossier)), '/');

        if ($dossier === '' || ! preg_match('#^([A-Za-z]:/|/)#', $dossier) || str_contains($dossier, '..')) {
            throw new SauvegardeException('Indiquez un chemin absolu (ex. /home/compte/sauvegardes ou C:/sauvegardes), sans « .. ».');
        }

        if (self::estDans($dossier, public_path())) {
            throw new SauvegardeException('Le dossier ne doit pas être dans le dossier public : les sauvegardes seraient téléchargeables par tous.');
        }

        // Le déploiement (rsync --delete) remplace tout le projet sauf storage/.
        if (self::estDans($dossier, base_path()) && ! self::estDans($dossier, storage_path('app'))) {
            throw new SauvegardeException('Dans le dossier du projet, seul storage/app/ est conservé à chaque déploiement : choisissez un dossier dans storage/app/ (par défaut '.self::dossierParDefaut().') ou hors du projet.');
        }

        if (! is_dir($dossier) && ! @mkdir($dossier, 0750, true)) {
            throw new SauvegardeException('Impossible de créer ce dossier : vérifiez le chemin et les droits.');
        }

        if (! is_writable($dossier)) {
            throw new SauvegardeException('Ce dossier n\'est pas accessible en écriture.');
        }

        return $dossier;
    }

    private static function estDans(string $dossier, string $parent): bool
    {
        $normaliser = fn (string $chemin): string => mb_strtolower(rtrim(str_replace('\\', '/', $chemin), '/')).'/';

        return str_starts_with($normaliser($dossier), $normaliser($parent));
    }

    /**
     * @return list<array{nom: string, taille: int, date: CarbonImmutable}>
     */
    public function lister(): array
    {
        if (! is_dir($this->dossier())) {
            return [];
        }

        return collect(File::files($this->dossier()))
            ->filter(fn ($fichier) => preg_match(self::MOTIF_FICHIER, $fichier->getFilename()) === 1)
            ->map(fn ($fichier) => [
                'nom' => $fichier->getFilename(),
                'taille' => $fichier->getSize(),
                'date' => CarbonImmutable::createFromTimestamp($fichier->getMTime())->setTimezone(config('app.timezone')),
            ])
            ->sortByDesc(fn (array $s) => $s['nom'])
            ->values()->all();
    }

    /**
     * @throws SauvegardeException
     */
    public function creer(?User $auteur, string $suffixe = ''): string
    {
        $dossier = self::verifierDossier($this->dossier());
        $nom = 'advantage-'.now()->format('Y-m-d-His').$suffixe.'.sql.gz';
        $sql = $dossier.'/'.$nom.'.tmp.sql';

        try {
            $this->moteur->exporter($sql);
            $this->compresser($sql, $dossier.'/'.$nom);
        } finally {
            @unlink($sql);
        }

        JournaliserAudit::enregistrer('sauvegarde.creee', donnees: ['fichier' => $nom, 'taille' => filesize($dossier.'/'.$nom)], acteur: $auteur);
        $this->appliquerRetention();

        return $nom;
    }

    /**
     * Remplace toute la base par la sauvegarde choisie. Une sauvegarde de
     * sécurité de l'état actuel est faite juste avant ; le schéma et les
     * permissions sont ensuite remis à niveau (sauvegarde plus ancienne).
     *
     * @return string nom de la sauvegarde de sécurité
     *
     * @throws SauvegardeException
     */
    public function restaurer(string $nom, User $auteur): string
    {
        $fichier = $this->chemin($nom);
        $securite = $this->creer($auteur, '-avant-restauration');
        $sql = $this->dossier().'/restauration-'.now()->format('YmdHis').'.tmp.sql';

        try {
            $this->decompresser($fichier, $sql);
            $this->moteur->importer($sql);
        } finally {
            @unlink($sql);
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('permissions:synchroniser');
        Cache::forget(Parametres::CLE_CACHE);
        EcheancesCartes::oublier();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Inscrit après l'import : le journal vient d'être remplacé par celui de la sauvegarde.
        JournaliserAudit::enregistrer('sauvegarde.restauree', donnees: ['fichier' => $nom, 'securite' => $securite], acteur: $auteur);

        return $securite;
    }

    /**
     * @throws SauvegardeException
     */
    public function chemin(string $nom): string
    {
        $chemin = $this->dossier().'/'.$nom;

        if (preg_match(self::MOTIF_FICHIER, $nom) !== 1 || ! is_file($chemin)) {
            throw new SauvegardeException('Sauvegarde introuvable.');
        }

        return $chemin;
    }

    /**
     * @throws SauvegardeException
     */
    public function supprimer(string $nom, User $auteur): void
    {
        unlink($this->chemin($nom));
        JournaliserAudit::enregistrer('sauvegarde.supprimee', donnees: ['fichier' => $nom], acteur: $auteur);
    }

    /**
     * Ne garde que les N sauvegardes les plus récentes (sécurité comprises).
     */
    private function appliquerRetention(): void
    {
        foreach (array_slice($this->lister(), (int) config('plateforme.sauvegardes.conserver', 10)) as $ancienne) {
            @unlink($this->dossier().'/'.$ancienne['nom']);
        }
    }

    private function compresser(string $source, string $cible): void
    {
        $entree = fopen($source, 'rb');
        $sortie = gzopen($cible, 'wb6');

        while (! feof($entree)) {
            gzwrite($sortie, (string) fread($entree, 1024 * 512));
        }

        fclose($entree);
        gzclose($sortie);
    }

    private function decompresser(string $source, string $cible): void
    {
        $entree = gzopen($source, 'rb');
        $sortie = fopen($cible, 'wb');

        while (! gzeof($entree)) {
            fwrite($sortie, (string) gzread($entree, 1024 * 512));
        }

        gzclose($entree);
        fclose($sortie);
    }
}
