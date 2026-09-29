<?php

namespace App\Actions\Partenaire;

use App\Enums\StatutDemandeOtp;
use App\Enums\TypeSms;
use App\Exceptions\OperationPartenaireException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\JournaliserAudit;
use App\Services\Sms\EnvoiSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Génère un code à usage unique pour une carte et l'envoie par SMS au
 * titulaire. Le code n'est jamais stocké ni journalisé en clair.
 */
class DemanderOtpAction
{
    public function __construct(private EnvoiSms $envoiSms) {}

    /**
     * @throws OperationPartenaireException
     */
    public function __invoke(string $numeroCarte, Partenaire $partenaire, User $operateur): DemandeOtp
    {
        return DB::transaction(function () use ($numeroCarte, $partenaire, $operateur): DemandeOtp {
            $carte = Carte::query()->with('titulaire')->where('numero_carte', $numeroCarte)->lockForUpdate()->first();

            if ($carte?->estUtilisable() !== true) {
                throw OperationPartenaireException::carteNonValide();
            }

            $this->limiterParCarte($carte);

            // Un seul code actif par carte et par partenaire : les précédents sont annulés.
            DemandeOtp::query()
                ->where('carte_id', $carte->id)
                ->where('partenaire_id', $partenaire->id)
                ->where('statut', StatutDemandeOtp::EnAttente)
                ->update(['statut' => StatutDemandeOtp::Expiree, 'updated_at' => now()]);

            $code = $this->genererCode();

            $demande = DemandeOtp::create([
                'carte_id' => $carte->id,
                'partenaire_id' => $partenaire->id,
                'demandee_par_id' => $operateur->id,
                'code_hash' => Hash::make($code),
                'demandee_le' => now(),
                'expire_le' => now()->addMinutes((int) config('plateforme.otp.duree_minutes')),
                'statut' => StatutDemandeOtp::EnAttente,
            ]);

            $this->envoiSms->envoyer($carte->titulaire->telephone, $this->message($code, $partenaire), TypeSms::Otp);

            JournaliserAudit::enregistrer('otp.demande', $demande, [
                'numero_carte' => $carte->numeroFormate(),
                'partenaire' => $partenaire->nom,
            ], $operateur);

            return $demande;
        });
    }

    /**
     * Anti-harcèlement : nombre de codes par carte borné sur 15 minutes et
     * sur la journée, tous partenaires confondus.
     */
    private function limiterParCarte(Carte $carte): void
    {
        $limites = [
            ['otp-carte-15min:'.$carte->id, (int) config('plateforme.otp.codes_par_carte_15_minutes'), 15 * 60],
            ['otp-carte-jour:'.$carte->id, (int) config('plateforme.otp.codes_par_carte_par_jour'), 24 * 3600],
        ];

        foreach ($limites as [$cle, $maximum]) {
            if (RateLimiter::tooManyAttempts($cle, $maximum)) {
                throw OperationPartenaireException::tropDeCodes(RateLimiter::availableIn($cle));
            }
        }

        foreach ($limites as [$cle, , $duree]) {
            RateLimiter::hit($cle, $duree);
        }
    }

    private function genererCode(): string
    {
        $longueur = (int) config('plateforme.otp.longueur', 6);

        return str_pad((string) random_int(0, 10 ** $longueur - 1), $longueur, '0', STR_PAD_LEFT);
    }

    /**
     * Le nom du partenaire permet au titulaire de repérer une demande qu'il n'a pas faite.
     */
    private function message(string $code, Partenaire $partenaire): string
    {
        $duree = (int) config('plateforme.otp.duree_minutes');

        return 'ADVANTAGE : votre code de validation chez '.Str::limit($partenaire->nom, 30, '')
            ." est {$code}. Valable {$duree} min. Ne le communiquez qu'en caisse.";
    }
}
