<?php

namespace App\Actions\Cartes;

use App\Enums\StatutCarte;
use App\Models\Carte;

/**
 * Passe au statut « expirée » les cartes actives dont l'échéance est
 * dépassée. L'affichage les montrait déjà expirées (statut effectif) : le
 * statut en base est aligné pour les rapports, et l'expiration est inscrite
 * dans l'historique permanent comme opération système (CarteObserver).
 * Une carte expirée n'est jamais réactivée.
 */
class MarquerCartesExpirees
{
    /**
     * @return int nombre de cartes passées à « expirée »
     */
    public function __invoke(): int
    {
        $nombre = 0;

        Carte::query()
            ->where('statut', StatutCarte::Active)
            ->where('expire_le', '<=', now())
            ->lazyById(200)
            ->each(function (Carte $carte) use (&$nombre): void {
                $carte->forceFill(['statut' => StatutCarte::Expiree, 'motif_statut' => 'Expiration automatique (12 mois)'])->save();
                $nombre++;
            });

        return $nombre;
    }
}
