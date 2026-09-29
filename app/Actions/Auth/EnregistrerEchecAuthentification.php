<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\JournaliserAudit;

/**
 * Comptabilise un échec de saisie du PIN (connexion ou confirmation) et
 * verrouille le compte au franchissement du seuil. Atomique : un seul
 * verrouillage est journalisé même en cas de requêtes concurrentes.
 */
class EnregistrerEchecAuthentification
{
    public function __invoke(User $user, string $contexte): void
    {
        User::query()->whereKey($user->id)->increment('tentatives_echouees');

        JournaliserAudit::enregistrer($contexte.'.echec', $user, ['nom_utilisateur' => $user->nom_utilisateur, 'motif' => 'secret_invalide', 'navigateur' => JournaliserAudit::navigateur()]);

        $verrouille = User::query()
            ->whereKey($user->id)
            ->whereNull('verrouille_le')
            ->where('tentatives_echouees', '>=', (int) config('plateforme.connexion.echecs_avant_verrouillage'))
            ->update(['verrouille_le' => now()]);

        if ($verrouille === 1) {
            JournaliserAudit::enregistrer('compte.verrouille', $user, ['motif' => 'echecs_'.$contexte]);
        }
    }
}
