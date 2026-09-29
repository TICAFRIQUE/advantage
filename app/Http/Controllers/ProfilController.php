<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mon profil (back-office et espace partenaire) : informations du compte
 * connecté et droits dont il dispose, en lecture seule. Le PIN et la fiche
 * se modifient par un administrateur (jamais sur son propre compte).
 */
class ProfilController extends Controller
{
    public function __invoke(Request $request): View
    {
        $compte = $request->user()->load(['roles.permissions', 'creePar.roles', 'modifiePar.roles', 'partenaire']);
        $superadmin = $compte->hasRole(Role::Superadmin);

        // Droits regroupés comme dans la configuration (le superadmin a tous les droits).
        $detenues = $compte->getAllPermissions()->pluck('name')->all();
        $groupes = collect(config('permissions.groupes'))
            ->map(fn (array $groupe) => [
                'libelle' => $groupe['libelle'],
                'permissions' => collect(array_keys($groupe['permissions']))
                    ->filter(fn (string $nom) => $superadmin || in_array($nom, $detenues, true))
                    ->map(fn (string $nom) => Permission::from($nom)->libelle())
                    ->values()->all(),
            ])
            ->filter(fn (array $groupe) => $groupe['permissions'] !== [])
            ->values();

        return view('profil', ['compte' => $compte, 'groupes' => $groupes, 'superadmin' => $superadmin]);
    }
}
