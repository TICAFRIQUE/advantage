<?php

namespace App\Actions\Agent;

use App\Enums\StatutCarte;
use App\Exceptions\ActionCarteImpossibleException;
use App\Models\Carte;
use Illuminate\Support\Facades\DB;

/**
 * Déclare une carte perdue : elle est révoquée définitivement.
 * Le contrôle d'état est fait sous verrou (et non seulement dans la policy,
 * que le superadmin contourne via Gate::before).
 */
class DeclarerPerteAction
{
    /**
     * @throws ActionCarteImpossibleException
     */
    public function __invoke(Carte $carte, string $motif): Carte
    {
        return DB::transaction(function () use ($carte, $motif): Carte {
            $carte = Carte::query()->lockForUpdate()->findOrFail($carte->id);

            if (! $carte->peutEtreDeclareePerdue()) {
                throw new ActionCarteImpossibleException(
                    "La carte {$carte->numeroFormate()} est {$carte->statutEffectif()->libelle()} : elle ne peut pas être déclarée perdue."
                );
            }

            $carte->update([
                'statut' => StatutCarte::Revoquee,
                'motif_statut' => 'Perte déclarée : '.$motif,
            ]);

            return $carte;
        });
    }
}
