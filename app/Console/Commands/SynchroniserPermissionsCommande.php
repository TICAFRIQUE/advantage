<?php

namespace App\Console\Commands;

use App\Services\Droits\SynchroniserPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * À lancer à chaque déploiement (production comme local) : sans risque de
 * doublon ni d'écrasement des réglages faits dans Paramètres.
 */
#[Signature('permissions:synchroniser
    {--supprimer-obsoletes : Supprime les permissions absentes de config/permissions.php}')]
#[Description('Synchronise les rôles et permissions de config/permissions.php (additif, idempotent)')]
class SynchroniserPermissionsCommande extends Command
{
    public function handle(SynchroniserPermissions $synchroniser): int
    {
        $rapport = $synchroniser((bool) $this->option('supprimer-obsoletes'));

        $this->components->twoColumnDetail('Rôles créés', $this->liste($rapport['roles_crees']));
        $this->components->twoColumnDetail('Permissions créées', $this->liste($rapport['permissions_creees']));

        if ($rapport['incoherences_retirees'] !== []) {
            $this->components->warn("Permissions retirées car hors de l'espace du rôle : ".implode(', ', $rapport['incoherences_retirees']));
        }

        if ($rapport['supprimees'] !== []) {
            $this->components->twoColumnDetail('Permissions obsolètes supprimées', $this->liste($rapport['supprimees']));
        } elseif ($rapport['obsoletes'] !== []) {
            $this->components->warn('Permissions obsolètes conservées : '.implode(', ', $rapport['obsoletes'])
                .'. Relancez avec --supprimer-obsoletes pour les retirer.');
        }

        $this->components->info('Synchronisation terminée. Les attributions existantes n\'ont pas été modifiées.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $elements
     */
    private function liste(array $elements): string
    {
        return $elements === [] ? 'aucun' : implode(', ', $elements);
    }
}
