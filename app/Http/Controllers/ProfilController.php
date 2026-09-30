<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mon profil (back-office et espace partenaire) : informations du compte
 * connecté et ses dernières actions (journal d'audit), en lecture seule. Le
 * PIN et la fiche se modifient par un administrateur (jamais sur son propre
 * compte), sauf le mot de passe du superadmin (MotDePasseController).
 */
class ProfilController extends Controller
{
    /**
     * Nombre d'actions affichées dans « Mon activité récente ».
     */
    public const ACTIVITES = 20;

    public function __invoke(Request $request): View
    {
        $compte = $request->user()->load(['roles', 'creePar.roles', 'modifiePar.roles', 'partenaire']);

        $activites = $compte->journauxAudit()
            ->latest('cree_le')->latest('id')
            ->limit(self::ACTIVITES)
            ->get();

        return view('profil', [
            'compte' => $compte,
            'activites' => $activites,
            'superadmin' => $compte->hasRole(Role::Superadmin),
        ]);
    }
}
