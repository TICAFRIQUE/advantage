<?php

namespace App\Services\Sauvegardes;

use Symfony\Component\Process\Process;

/**
 * Moteur MySQL : mysqldump / mysql en ligne de commande. Le mot de passe
 * passe par la variable d'environnement MYSQL_PWD, jamais par les
 * arguments (visibles dans la liste des processus).
 */
class MoteurMysql implements MoteurSauvegarde
{
    public function exporter(string $fichierSql): void
    {
        $this->executer([
            (string) config('plateforme.sauvegardes.mysqldump'),
            ...$this->connexion(),
            '--single-transaction', '--routines', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--add-drop-table',
            '--result-file='.$fichierSql,
            (string) config('database.connections.mysql.database'),
        ]);
    }

    public function importer(string $fichierSql): void
    {
        $entree = fopen($fichierSql, 'rb');

        try {
            $this->executer([
                (string) config('plateforme.sauvegardes.mysql'),
                ...$this->connexion(),
                '--default-character-set=utf8mb4',
                (string) config('database.connections.mysql.database'),
            ], $entree);
        } finally {
            if (is_resource($entree)) {
                fclose($entree);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function connexion(): array
    {
        return [
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
        ];
    }

    /**
     * @param  list<string>  $commande
     * @param  resource|null  $entree
     */
    private function executer(array $commande, $entree = null): void
    {
        $processus = new Process($commande, base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')], $entree, 600);
        $processus->run();

        if (! $processus->isSuccessful()) {
            // Message sans le mot de passe (jamais dans les arguments).
            throw new SauvegardeException('Échec de '.basename($commande[0]).' : '.trim($processus->getErrorOutput() ?: $processus->getOutput()));
        }
    }
}
