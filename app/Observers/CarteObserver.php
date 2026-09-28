<?php

namespace App\Observers;

use App\Models\Carte;
use App\Services\JournaliserAudit;

class CarteObserver
{
    public function created(Carte $carte): void
    {
        JournaliserAudit::enregistrer('carte.activee', $carte, [
            'apres' => [
                'numero_carte' => $carte->numero_carte,
                'titulaire_id' => $carte->titulaire_id,
                'statut' => $carte->statut->value,
                'active_le' => $carte->active_le?->toDateTimeString(),
                'expire_le' => $carte->expire_le?->toDateTimeString(),
            ],
        ]);
    }

    public function updated(Carte $carte): void
    {
        $modifies = array_values(array_diff(array_keys($carte->getChanges()), ['updated_at', 'modifie_par_id']));

        if ($modifies === []) {
            return;
        }

        JournaliserAudit::enregistrer(
            in_array('statut', $modifies, true) ? 'carte.statut_modifie' : 'carte.modifiee',
            $carte,
            [
                'avant' => array_intersect_key($carte->getRawOriginal(), array_flip($modifies)),
                'apres' => array_intersect_key($carte->getAttributes(), array_flip($modifies)),
            ],
        );
    }

    public function deleted(Carte $carte): void
    {
        JournaliserAudit::enregistrer('carte.supprimee', $carte);
    }
}
