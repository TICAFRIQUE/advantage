<?php

namespace App\Actions\Gestion;

use App\Enums\StatutLivraison;
use App\Models\AlerteExpiration;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\MessageSms;
use App\Models\OperationCarte;
use App\Models\SuppressionCarte;
use App\Models\Titulaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcheancesCartes;
use App\Services\JournaliserAudit;
use App\Services\StatistiquesTableauDeBord;
use Illuminate\Support\Facades\DB;

/**
 * Seul chemin autorisé pour supprimer physiquement une carte (cartes de test
 * créées en production) : transactions, codes OTP, alertes, historique des
 * opérations, puis la carte et — s'il n'a aucune autre carte — son titulaire
 * et les SMS qui lui ont été adressés. Le numéro et le téléphone redeviennent
 * utilisables.
 *
 * Tout ou rien (une seule transaction SQL). L'opération est inscrite au
 * registre `suppressions_cartes`, jamais effaçable, et au journal d'audit.
 * Irréversible : seule une sauvegarde permet de revenir en arrière.
 */
class SupprimerCarteDefinitivementAction
{
    public function __invoke(Carte $carte, User $auteur, string $motif): SuppressionCarte
    {
        $suppression = DB::transaction(function () use ($carte, $auteur, $motif): SuppressionCarte {
            $carte = Carte::withTrashed()->lockForUpdate()->findOrFail($carte->id);
            $titulaire = Titulaire::withTrashed()->lockForUpdate()->findOrFail($carte->titulaire_id);

            // Ordre imposé par les clés étrangères : transactions → codes OTP → alertes → opérations → carte.
            $transactions = Transaction::query()->where('carte_id', $carte->id)->toBase()->delete();
            $codes = DemandeOtp::query()->where('carte_id', $carte->id)->toBase()->delete();

            $smsAlertes = AlerteExpiration::query()->where('carte_id', $carte->id)->whereNotNull('message_sms_id')->pluck('message_sms_id');
            $alertes = AlerteExpiration::query()->where('carte_id', $carte->id)->toBase()->delete();

            $operations = $this->supprimerOperations($carte);

            // Événement « deleted » : caches invalidés par CarteObserver.
            $carte->forceDelete();

            $titulaireSupprime = ! Carte::withTrashed()->where('titulaire_id', $titulaire->id)->exists();

            // SMS en attente conservés : leur job d'envoi les référence encore.
            $sms = MessageSms::query()
                ->where('statut', '!=', StatutLivraison::EnAttente)
                ->where(fn ($q) => $q
                    ->whereIn('id', $smsAlertes)
                    ->when($titulaireSupprime, fn ($q) => $q->orWhere('telephone', $titulaire->telephone)))
                ->toBase()->delete();

            if ($titulaireSupprime) {
                $titulaire->forceDelete();
            }

            $suppression = SuppressionCarte::create([
                'numero_carte' => $carte->numero_carte,
                'supprime_par_id' => $auteur->id,
                'motif' => $motif,
                'transactions_supprimees' => $transactions,
                'codes_supprimes' => $codes,
                'alertes_supprimees' => $alertes,
                'operations_supprimees' => $operations,
                'sms_supprimes' => $sms,
                'titulaire_supprime' => $titulaireSupprime,
            ]);

            JournaliserAudit::enregistrer('carte.supprimee_definitivement', $carte, [
                'numero_carte' => $carte->numeroFormate(),
                'motif' => $motif,
                'transactions_supprimees' => $transactions,
                'codes_supprimes' => $codes,
                'operations_supprimees' => $operations,
                'titulaire_supprime' => $titulaireSupprime,
            ], $auteur);

            return $suppression;
        });

        // Après validation de la transaction : une requête concurrente a pu
        // remettre en cache des chiffres calculés avant la suppression.
        EcheancesCartes::oublier();
        StatistiquesTableauDeBord::oublier();

        return $suppression;
    }

    /**
     * `operations_cartes` est en ajout seul : le trigger ne laisse passer la
     * suppression que le temps où cette session lève le verrou.
     */
    private function supprimerOperations(Carte $carte): int
    {
        DB::statement('SET @autoriser_suppression_carte = 1');

        try {
            return OperationCarte::query()->where('carte_id', $carte->id)->toBase()->delete();
        } finally {
            DB::statement('SET @autoriser_suppression_carte = NULL');
        }
    }
}
