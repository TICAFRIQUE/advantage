<?php

namespace App\Observers;

use App\Models\User;
use App\Services\JournaliserAudit;

/**
 * Journalise le cycle de vie des comptes. Le PIN n'est jamais journalisé :
 * seul le fait qu'il ait été réinitialisé est tracé.
 */
class UserObserver
{
    /**
     * Champs techniques qui ne constituent pas un changement métier (la
     * suppression et la restauration ont leurs propres entrées).
     *
     * @var list<string>
     */
    private const CHAMPS_IGNORES = ['updated_at', 'remember_token', 'tentatives_echouees', 'derniere_connexion_le', 'deleted_at', 'supprime_par_id'];

    public function created(User $user): void
    {
        JournaliserAudit::enregistrer('utilisateur.cree', $user, [
            'apres' => $user->only(['nom', 'nom_utilisateur', 'partenaire_id', 'statut']),
        ]);
    }

    public function updated(User $user): void
    {
        $modifies = array_diff(array_keys($user->getChanges()), self::CHAMPS_IGNORES);

        if (in_array('password', $modifies, true)) {
            JournaliserAudit::enregistrer('utilisateur.pin_reinitialise', $user);
        }

        if (in_array('verrouille_le', $modifies, true)) {
            JournaliserAudit::enregistrer($user->verrouille_le ? 'compte.verrouille' : 'compte.deverrouille', $user);
        }

        $metier = array_values(array_diff($modifies, ['password', 'verrouille_le']));

        if ($metier !== []) {
            JournaliserAudit::enregistrer('utilisateur.modifie', $user, [
                'avant' => array_intersect_key($user->getOriginal(), array_flip($metier)),
                'apres' => $user->only($metier),
            ]);
        }
    }

    public function deleted(User $user): void
    {
        JournaliserAudit::enregistrer('utilisateur.supprime', $user);
    }

    public function restored(User $user): void
    {
        JournaliserAudit::enregistrer('utilisateur.restaure', $user);
    }
}
