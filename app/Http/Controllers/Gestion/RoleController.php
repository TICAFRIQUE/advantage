<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Droits\EnregistrerRoleAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\OperationRoleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\EnregistrerRoleRequest;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Paramètres › Rôles et permissions : matrice des droits, rôles personnalisés
 * du back-office. Les cases modifiables sont calculées par GardeDroits et
 * revérifiées par EnregistrerRoleAction.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = $this->roles();

        return view('gestion.roles.index', [
            'roles' => $roles,
            'groupes' => $this->groupes(),
            'peutCreer' => $request->user()->can(Permission::GererRoles->value),
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = $request->user();

        return view('gestion.roles.formulaire', [
            'role' => null,
            'groupes' => $this->groupes('gestion'),
            'cochees' => [],
            // Nouveau rôle : on n'attribue que des permissions du back-office que l'on détient.
            'modifiables' => array_values(array_filter(
                Permission::cases(),
                fn (Permission $p) => $p->espace() === 'gestion' && ($acteur->hasRole(Role::Superadmin) || $acteur->can($p->value)),
            )),
        ]);
    }

    public function store(EnregistrerRoleRequest $request, EnregistrerRoleAction $enregistrer): RedirectResponse
    {
        try {
            $role = $enregistrer->creer($request->libelle(), $request->permissions(), $request->user());
        } catch (OperationRoleException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('gestion.roles.index')
            ->with('succes', "Le rôle « {$role->libelle()} » est créé : attribuez-le depuis Paramètres › Utilisateurs.");
    }

    public function edit(Request $request, RoleUtilisateur $role): View
    {
        abort_if($role->estVerrouille(), 404);

        $acteur = $request->user();
        $role->load('permissions');

        return view('gestion.roles.formulaire', [
            'role' => $role,
            'groupes' => $this->groupes($role->espace()),
            'cochees' => $role->permissions->pluck('name')->all(),
            'modifiables' => array_values(array_filter(
                Permission::cases(),
                fn (Permission $p) => GardeDroits::peutModifierPermissionDuRole($acteur, $role, $p),
            )),
            'peutRenommer' => GardeDroits::peutGererRole($acteur, $role),
            'nombreComptes' => User::withTrashed()->role($role->name)->count(),
        ]);
    }

    public function update(EnregistrerRoleRequest $request, RoleUtilisateur $role, EnregistrerRoleAction $enregistrer): RedirectResponse
    {
        try {
            $enregistrer->modifier($role, $request->libelle(), $request->permissions(), $request->user());
        } catch (OperationRoleException $exception) {
            return back()->withInput()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('gestion.roles.index')->with('succes', "Le rôle « {$role->fresh()->libelle()} » est mis à jour.");
    }

    public function destroy(Request $request, RoleUtilisateur $role, EnregistrerRoleAction $enregistrer): RedirectResponse
    {
        try {
            $enregistrer->supprimer($role, $request->user());
        } catch (OperationRoleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('gestion.roles.index')->with('succes', "Le rôle « {$role->libelle()} » est supprimé.");
    }

    /**
     * @return Collection<int, RoleUtilisateur>
     */
    private function roles(): Collection
    {
        return RoleUtilisateur::query()->with('permissions')->get()
            ->each(fn (RoleUtilisateur $role) => $role->setAttribute('nombre_comptes', User::query()->role($role->name)->count()))
            ->sortBy(fn (RoleUtilisateur $role) => [$role->espace() === 'gestion' ? 0 : 1, $role->rang(), $role->libelle()])
            ->values();
    }

    /**
     * Groupes de permissions de la configuration (hors espace « superadmin »,
     * jamais attribuable), éventuellement limités à un espace.
     *
     * @return list<array{libelle: string, espace: string, permissions: list<Permission>}>
     */
    private function groupes(?string $espace = null): array
    {
        $groupes = [];

        foreach (config('permissions.groupes') as $groupe) {
            if ($groupe['espace'] === 'superadmin' || ($espace !== null && $groupe['espace'] !== $espace)) {
                continue;
            }

            $groupes[] = [
                'libelle' => $groupe['libelle'],
                'espace' => $groupe['espace'],
                'permissions' => array_map(fn (string $nom) => Permission::from($nom), array_keys($groupe['permissions'])),
            ];
        }

        return $groupes;
    }
}
