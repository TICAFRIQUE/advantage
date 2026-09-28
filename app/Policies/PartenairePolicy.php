<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Partenaire;
use App\Models\User;

class PartenairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoirPartenaires->value);
    }

    public function view(User $user, Partenaire $partenaire): bool
    {
        return $user->can(Permission::VoirPartenaires->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::GererPartenaires->value);
    }

    public function update(User $user, Partenaire $partenaire): bool
    {
        return $user->can(Permission::GererPartenaires->value);
    }

    public function delete(User $user, Partenaire $partenaire): bool
    {
        return $user->can(Permission::SupprimerPartenaires->value);
    }

    public function gererOperateurs(User $user, Partenaire $partenaire): bool
    {
        return $user->can(Permission::GererOperateursPartenaires->value);
    }
}
