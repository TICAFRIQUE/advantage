<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\JournaliserAudit;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        JournaliserAudit::enregistrer('transaction.creee', $transaction, [
            'apres' => [
                'carte_id' => $transaction->carte_id,
                'partenaire_id' => $transaction->partenaire_id,
                'taux_applique' => (string) $transaction->taux_applique,
                'valide_par_id' => $transaction->valide_par_id,
            ],
        ]);
    }
}
