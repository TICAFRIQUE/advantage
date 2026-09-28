<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartenaireCourant;

/**
 * Un partenaire ne voit que ses propres transactions.
 */
class TransactionPolicy
{
    /**
     * Historique de l'espace partenaire.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoirHistoriqueTransactions->value) && PartenaireCourant::pour($user) !== null;
    }

    /**
     * Écran de résultat après validation (opérateur ou back-office agissant
     * pour ce partenaire).
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return DemandeOtpPolicy::peutEffectuerTransaction($user)
            && $transaction->partenaire_id === PartenaireCourant::pour($user)?->id;
    }
}
