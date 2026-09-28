<?php

namespace App\Actions\Gestion;

use App\Enums\StatutCarte;
use App\Enums\StatutDemandeOtp;
use App\Exceptions\ActionCarteImpossibleException;
use App\Models\Carte;
use App\Models\DemandeOtp;
use Illuminate\Support\Facades\DB;

/**
 * Suspendre, réactiver ou révoquer une carte (motif obligatoire).
 *
 * La transition est contrôlée sous verrou, ici et non seulement dans la
 * policy (que le superadmin contourne via Gate::before). Une carte suspendue
 * ou révoquée voit ses codes OTP en attente annulés immédiatement.
 */
class ChangerStatutCarteAction
{
    /**
     * @throws ActionCarteImpossibleException
     */
    public function __invoke(Carte $carte, StatutCarte $cible, string $motif): Carte
    {
        return DB::transaction(function () use ($carte, $cible, $motif): Carte {
            $carte = Carte::query()->lockForUpdate()->findOrFail($carte->id);

            if (! in_array($cible, $carte->transitionsPossibles(), true)) {
                throw new ActionCarteImpossibleException(
                    "La carte {$carte->numeroFormate()} est {$carte->statutEffectif()->libelle()} : elle ne peut pas passer à « {$cible->libelle()} »."
                );
            }

            $carte->update([
                'statut' => $cible,
                'motif_statut' => $this->libelleMotif($cible, $motif),
            ]);

            if ($cible !== StatutCarte::Active) {
                DemandeOtp::query()
                    ->where('carte_id', $carte->id)
                    ->where('statut', StatutDemandeOtp::EnAttente)
                    ->update(['statut' => StatutDemandeOtp::Expiree, 'updated_at' => now()]);
            }

            return $carte;
        });
    }

    private function libelleMotif(StatutCarte $cible, string $motif): string
    {
        return match ($cible) {
            StatutCarte::Suspendue => 'Suspension : '.$motif,
            StatutCarte::Revoquee => 'Révocation : '.$motif,
            StatutCarte::Active => 'Réactivation : '.$motif,
            default => $motif,
        };
    }
}
