<?php

namespace App\Observers;

use App\Models\Titulaire;
use App\Services\JournaliserAudit;

class TitulaireObserver
{
    /**
     * @var list<string>
     */
    private const CHAMPS_TRACES = ['nom', 'prenom', 'telephone', 'statut'];

    public function created(Titulaire $titulaire): void
    {
        JournaliserAudit::enregistrer('titulaire.cree', $titulaire, [
            'apres' => array_intersect_key($titulaire->getAttributes(), array_flip(self::CHAMPS_TRACES)),
        ]);
    }

    public function updated(Titulaire $titulaire): void
    {
        $modifies = array_values(array_intersect(array_keys($titulaire->getChanges()), self::CHAMPS_TRACES));

        if ($modifies === []) {
            return;
        }

        JournaliserAudit::enregistrer('titulaire.modifie', $titulaire, [
            'avant' => array_intersect_key($titulaire->getRawOriginal(), array_flip($modifies)),
            'apres' => array_intersect_key($titulaire->getAttributes(), array_flip($modifies)),
        ]);
    }
}
