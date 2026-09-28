<?php

namespace App\Actions\Partenaire;

use App\Enums\StatutDemandeOtp;
use App\Enums\StatutTransaction;
use App\Exceptions\OperationPartenaireException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JournaliserAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Valide le code saisi en caisse et crée la transaction (passage + taux).
 *
 * - Idempotent : une double soumission renvoie la transaction existante
 *   (verrou FOR UPDATE + contrainte UNIQUE transactions.demande_otp_id).
 * - Les échecs sont enregistrés AVANT de lever l'erreur : le compteur
 *   d'essais n'est jamais annulé par un rollback.
 * - La carte est revérifiée au moment de la validation (révoquée entre-temps ?).
 */
class ValiderOtpAction
{
    /**
     * @throws OperationPartenaireException
     */
    public function __invoke(DemandeOtp $demande, string $code, Partenaire $partenaire, User $operateur): Transaction
    {
        try {
            $resultat = DB::transaction(fn (): Transaction|OperationPartenaireException => $this->valider($demande, $code, $partenaire, $operateur));
        } catch (UniqueConstraintViolationException) {
            // Validation concurrente déjà enregistrée : on renvoie celle-ci.
            $resultat = Transaction::query()->where('demande_otp_id', $demande->id)->firstOrFail();
        }

        if ($resultat instanceof OperationPartenaireException) {
            throw $resultat;
        }

        return $resultat;
    }

    private function valider(DemandeOtp $demande, string $code, Partenaire $partenaire, User $operateur): Transaction|OperationPartenaireException
    {
        $demande = DemandeOtp::query()->lockForUpdate()->findOrFail($demande->id);

        // Contrôlé ici aussi : le superadmin contourne les policies (Gate::before).
        if ($demande->partenaire_id !== $partenaire->id) {
            return OperationPartenaireException::codeNonValide();
        }

        if ($demande->statut === StatutDemandeOtp::Utilisee) {
            return $demande->transaction()->firstOrFail();
        }

        if ($demande->statut !== StatutDemandeOtp::EnAttente) {
            return OperationPartenaireException::codeNonValide();
        }

        if ($demande->estExpiree()) {
            $demande->update(['statut' => StatutDemandeOtp::Expiree]);

            return OperationPartenaireException::codeExpire();
        }

        if (! Hash::check($code, $demande->code_hash)) {
            return $this->enregistrerEchec($demande, $operateur);
        }

        $carte = Carte::query()->lockForUpdate()->findOrFail($demande->carte_id);

        if (! $carte->estUtilisable()) {
            $demande->update(['statut' => StatutDemandeOtp::Expiree]);

            return OperationPartenaireException::carteNonValide();
        }

        $demande->forceFill(['statut' => StatutDemandeOtp::Utilisee, 'utilisee_le' => now()])->save();

        $transaction = Transaction::create([
            'carte_id' => $carte->id,
            'partenaire_id' => $partenaire->id,
            'demande_otp_id' => $demande->id,
            'valide_par_id' => $operateur->id,
            'taux_applique' => $partenaire->taux_reduction,
            'validee_le' => now(),
            'statut' => StatutTransaction::Validee,
        ]);

        JournaliserAudit::enregistrer('otp.valide', $demande, ['transaction_id' => $transaction->id], $operateur);

        return $transaction;
    }

    private function enregistrerEchec(DemandeOtp $demande, User $operateur): OperationPartenaireException
    {
        $demande->increment('tentatives');
        $restants = max(0, (int) config('plateforme.otp.tentatives_max') - $demande->tentatives);

        if ($restants === 0) {
            $demande->update(['statut' => StatutDemandeOtp::Bloquee]);
        }

        JournaliserAudit::enregistrer('otp.echec', $demande, ['tentatives' => $demande->tentatives, 'bloque' => $restants === 0], $operateur);

        return OperationPartenaireException::codeIncorrect($restants);
    }
}
