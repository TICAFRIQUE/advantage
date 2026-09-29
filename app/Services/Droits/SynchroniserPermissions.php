<?php

namespace App\Services\Droits;

use App\Enums\Permission;
use App\Models\RoleUtilisateur as ModeleRole;
use Spatie\Permission\Models\Permission as ModelePermission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Synchronise config/permissions.php avec la base, de manière ADDITIVE :
 * - crée les rôles et permissions manquants, jamais de doublon ;
 * - les attributions par défaut ne s'appliquent qu'aux créations (nouvelle
 *   permission → rôles par défaut ; nouveau rôle → ses permissions par défaut) ;
 * - ne retire ni ne modifie jamais une attribution existante (les réglages
 *   faits dans Paramètres sont préservés) ;
 * - recopie le libellé et l'espace des rôles système (métadonnées, pas des réglages) ;
 * - retire toute permission portée par un rôle d'un autre espace, rôles
 *   personnalisés compris (intégrité) ;
 * - ne supprime les permissions obsolètes que sur demande explicite.
 */
class SynchroniserPermissions
{
    private const GARDE = 'web';

    /**
     * @return array{roles_crees: list<string>, permissions_creees: list<string>, incoherences_retirees: list<string>, obsoletes: list<string>, supprimees: list<string>}
     */
    public function __invoke(bool $supprimerObsoletes = false): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rapport = ['roles_crees' => [], 'permissions_creees' => [], 'incoherences_retirees' => [], 'obsoletes' => [], 'supprimees' => []];
        $definitions = Permission::definitions();

        foreach ($definitions as $nom => $definition) {
            $permission = ModelePermission::query()->firstOrCreate(['name' => $nom, 'guard_name' => self::GARDE]);

            if ($permission->wasRecentlyCreated) {
                $rapport['permissions_creees'][] = $nom;
            }
        }

        foreach (config('permissions.roles') as $nomRole => $definitionRole) {
            $role = ModeleRole::query()->firstOrCreate(['name' => $nomRole, 'guard_name' => self::GARDE]);
            $role->forceFill(['libelle' => $definitionRole['libelle'], 'espace' => $definitionRole['espace'], 'systeme' => true])->save();

            if ($role->wasRecentlyCreated) {
                $rapport['roles_crees'][] = $nomRole;
                // Nouveau rôle : toutes ses permissions par défaut.
                $role->givePermissionTo($this->parDefaut($definitions, $nomRole));

                continue;
            }

            // Rôle existant : seulement les permissions créées lors de cette exécution.
            $nouvelles = array_intersect($this->parDefaut($definitions, $nomRole), $rapport['permissions_creees']);

            if ($nouvelles !== []) {
                $role->givePermissionTo($nouvelles);
            }
        }

        $rapport['incoherences_retirees'] = $this->retirerPermissionsHorsEspace($definitions);

        $rapport['obsoletes'] = ModelePermission::query()
            ->where('guard_name', self::GARDE)
            ->whereNotIn('name', array_keys($definitions))
            ->pluck('name')->sort()->values()->all();

        if ($supprimerObsoletes && $rapport['obsoletes'] !== []) {
            ModelePermission::query()->whereIn('name', $rapport['obsoletes'])->where('guard_name', self::GARDE)->get()
                ->each(fn (ModelePermission $permission) => $permission->delete());
            $rapport['supprimees'] = $rapport['obsoletes'];
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $rapport;
    }

    /**
     * Intégrité : une permission d'un espace n'est jamais portée par un rôle
     * de l'autre espace (héritage d'un ancien modèle, erreur manuelle…). Ce
     * n'est pas un réglage légitime : l'attribution est retirée et signalée.
     *
     * @param  array<string, array{espace: string}>  $definitions
     * @return list<string> « rôle : permission » retirées
     */
    private function retirerPermissionsHorsEspace(array $definitions): array
    {
        $retirees = [];

        foreach (ModeleRole::query()->where('guard_name', self::GARDE)->with('permissions')->get() as $role) {
            foreach ($role->permissions as $permission) {
                $espace = $definitions[$permission->name]['espace'] ?? null;

                if ($espace !== null && $espace !== $role->espace()) {
                    $role->revokePermissionTo($permission);
                    $retirees[] = "{$role->name} : {$permission->name}";
                }
            }
        }

        return $retirees;
    }

    /**
     * @param  array<string, array{roles: list<string>}>  $definitions
     * @return list<string>
     */
    private function parDefaut(array $definitions, string $role): array
    {
        return array_keys(array_filter($definitions, fn (array $d) => in_array($role, $d['roles'], true)));
    }
}
