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
                'numero_carte' => $transaction->carte?->numeroFormate(),
                'partenaire' => $transaction->partenaire?->nom,
                'taux_applique' => rtrim(rtrim((string) $transaction->taux_applique, '0'), '.').' %',
                'validee_par' => $transaction->validePar?->libelleActeur(),
            ],
        ]);
    }
}
