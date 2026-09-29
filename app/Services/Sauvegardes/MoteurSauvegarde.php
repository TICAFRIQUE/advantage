<?php

namespace App\Services\Sauvegardes;

/**
 * Export et import de la base de données (fichier SQL non compressé).
 */
interface MoteurSauvegarde
{
    /**
     * Écrit le contenu complet de la base (structure, données, triggers)
     * dans $fichierSql.
     *
     * @throws SauvegardeException
     */
    public function exporter(string $fichierSql): void;

    /**
     * Remplace le contenu de la base par celui de $fichierSql.
     *
     * @throws SauvegardeException
     */
    public function importer(string $fichierSql): void;
}
