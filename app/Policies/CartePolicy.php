<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Carte;
use App\Models\User;

/**
 * Tous les agents agissent sur toutes les cartes (traçabilité par
 * active_par_id / modifie_par_id et journal d'audit).
 */
class CartePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RechercherCarte->value);
    }

    public function view(User $user, Carte $carte): bool
    {
        return $user->can(Permission::RechercherCarte->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ActiverCarte->value);
    }

    public function declarerPerte(User $user, Carte $carte): bool
    {
        return $user->can(Permission::SignalerCartePerdue->value) && $carte->peutEtreDeclareePerdue();
    }
}
