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
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoirSesTransactions->value) && PartenaireCourant::pour($user) !== null;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can(Permission::VoirSesTransactions->value)
            && $transaction->partenaire_id === PartenaireCourant::pour($user)?->id;
    }
}
