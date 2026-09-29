<?php

namespace App\Actions\Cartes;

use App\Enums\CanalAlerte;
use App\Enums\PalierAlerte;
use App\Enums\StatutCarte;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\AlerteExpiration;
use App\Models\Carte;
use App\Services\Sms\EnvoiSms;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Prévient par SMS les titulaires dont la carte active arrive à échéance
 * (paliers 3, 2 et 1 mois). Idempotent : une seule alerte par carte et par
 * palier (contrainte unique), même si la tâche est relancée ou tourne sur
 * deux serveurs. Si un palier a été manqué, seul le plus proche de
 * l'échéance est envoyé (jamais deux SMS le même jour).
 */
class EnvoyerAlertesExpiration
{
    public function __construct(private EnvoiSms $envoiSms) {}

    /**
     * @return array<string, int> nombre d'alertes envoyées par palier
     */
    public function __invoke(): array
    {
        $envoyees = array_fill_keys(PalierAlerte::valeurs(), 0);

        if (! config('plateforme.alertes_expiration.sms')) {
            return $envoyees;
        }

        $paliers = collect(PalierAlerte::cases())->sortBy(fn (PalierAlerte $p) => $p->mois())->values();
        $horizon = now()->addMonths($paliers->last()->mois());

        Carte::query()
            ->with('titulaire')
            ->where('statut', StatutCarte::Active)
            ->where('expire_le', '>', now())
            ->where('expire_le', '<=', $horizon)
            ->lazyById(200)
            ->each(function (Carte $carte) use ($paliers, &$envoyees): void {
                $palier = $paliers->first(fn (PalierAlerte $p) => $carte->expire_le->lte(now()->addMonths($p->mois())));

                if ($palier !== null && $carte->titulaire !== null && $this->alerter($carte, $palier)) {
                    $envoyees[$palier->value]++;
                }
            });

        return $envoyees;
    }

    private function alerter(Carte $carte, PalierAlerte $palier): bool
    {
        $existe = AlerteExpiration::query()
            ->where('carte_id', $carte->id)
            ->where('palier', $palier)
            ->where('canal', CanalAlerte::Sms)
            ->exists();

        if ($existe) {
            return false;
        }

        try {
            DB::transaction(function () use ($carte, $palier): void {
                $message = $this->envoiSms->envoyer($carte->titulaire->telephone, $this->texte($carte, $palier), TypeSms::AlerteExpiration);

                AlerteExpiration::create([
                    'carte_id' => $carte->id,
                    'palier' => $palier,
                    'canal' => CanalAlerte::Sms,
                    'message_sms_id' => $message->id,
                    'envoyee_le' => now(),
                    'statut_livraison' => StatutLivraison::EnAttente,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Envoyée entre-temps par une autre exécution : le SMS est annulé avec la transaction.
            return false;
        }

        return true;
    }

    private function texte(Carte $carte, PalierAlerte $palier): string
    {
        return strtr((string) config('plateforme.alertes_expiration.message'), [
            ':numero' => $carte->numeroFormate(),
            ':date' => $carte->expire_le->format('d/m/Y'),
            ':delai' => $palier->libelle(),
        ]);
    }
}
