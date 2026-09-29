<?php

namespace App\Observers;

use App\Enums\StatutCarte;
use App\Enums\TypeOperationCarte;
use App\Models\Carte;
use App\Models\OperationCarte;
use App\Services\JournaliserAudit;

/**
 * Journal d'audit (14 jours) + historique permanent des opérations.
 */
class CarteObserver
{
    public function created(Carte $carte): void
    {
        JournaliserAudit::enregistrer('carte.activee', $carte, [
            'apres' => [
                'numero_carte' => $carte->numeroFormate(),
                'titulaire' => $carte->titulaire?->nomComplet(),
                'telephone' => $carte->titulaire?->telephoneFormate(),
                'statut' => $carte->statut->value,
                'active_le' => $carte->active_le?->toDateTimeString(),
                'expire_le' => $carte->expire_le?->toDateTimeString(),
            ],
        ]);

        if ($carte->active_le !== null) {
            OperationCarte::create([
                'carte_id' => $carte->id,
                'type' => TypeOperationCarte::Activation,
                'effectuee_par_id' => $carte->active_par_id,
                'effectuee_le' => $carte->active_le,
            ]);
        }
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
                'numero_carte' => $carte->numeroFormate(),
                'avant' => array_intersect_key($carte->getRawOriginal(), array_flip($modifies)),
                'apres' => array_intersect_key($carte->getAttributes(), array_flip($modifies)),
            ],
        );

        if (in_array('statut', $modifies, true)) {
            $this->enregistrerChangementStatut($carte);
        }
    }

    public function deleted(Carte $carte): void
    {
        JournaliserAudit::enregistrer('carte.supprimee', $carte);
    }

    private function enregistrerChangementStatut(Carte $carte): void
    {
        $type = TypeOperationCarte::depuisChangementStatut(
            StatutCarte::tryFrom((string) $carte->getRawOriginal('statut')),
            $carte->statut,
        );

        if ($type !== null) {
            // L'expiration est automatique, même constatée pendant l'action d'un agent.
            OperationCarte::enregistrer($carte, $type, $carte->motif_statut, systeme: $type === TypeOperationCarte::Expiration);
        }
    }
}
