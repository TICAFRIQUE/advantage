<?php

namespace App\Observers;

use App\Models\RoleUtilisateur;
use App\Services\JournaliserAudit;
use Illuminate\Support\Arr;

/**
 * Cycle de vie des rôles personnalisés (les permissions ajoutées ou retirées
 * sont tracées par JournaliserChangementPermission).
 */
class RoleUtilisateurObserver
{
    public function created(RoleUtilisateur $role): void
    {
        JournaliserAudit::enregistrer('role.cree', $role, ['apres' => Arr::only($role->getAttributes(), ['name', 'libelle', 'espace'])]);
    }

    public function updated(RoleUtilisateur $role): void
    {
        if ($role->wasChanged('libelle')) {
            JournaliserAudit::enregistrer('role.renomme', $role, [
                'avant' => ['libelle' => $role->getOriginal('libelle')],
                'apres' => ['libelle' => $role->getAttributes()['libelle'] ?? null],
            ]);
        }
    }

    public function deleted(RoleUtilisateur $role): void
    {
        JournaliserAudit::enregistrer('role.supprime', $role, ['avant' => Arr::only($role->getAttributes(), ['name', 'libelle', 'espace'])]);
    }
}
