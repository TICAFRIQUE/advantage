<?php

namespace App\Observers;

use App\Models\Partenaire;
use App\Services\JournaliserAudit;

class PartenaireObserver
{
    /**
     * @var list<string>
     */
    private const CHAMPS_TRACES = ['nom', 'secteur', 'localisation', 'contact', 'taux_reduction', 'statut'];

    public function created(Partenaire $partenaire): void
    {
        JournaliserAudit::enregistrer('partenaire.cree', $partenaire, [
            'apres' => array_intersect_key($partenaire->getAttributes(), array_flip(self::CHAMPS_TRACES)),
        ]);
    }

    public function updated(Partenaire $partenaire): void
    {
        $modifies = array_values(array_intersect(array_keys($partenaire->getChanges()), self::CHAMPS_TRACES));

        if ($modifies === []) {
            return;
        }

        JournaliserAudit::enregistrer('partenaire.modifie', $partenaire, [
            'avant' => array_intersect_key($partenaire->getRawOriginal(), array_flip($modifies)),
            'apres' => array_intersect_key($partenaire->getAttributes(), array_flip($modifies)),
        ]);
    }
}
