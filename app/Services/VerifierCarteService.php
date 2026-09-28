<?php

namespace App\Services;

use App\Models\Carte;
use App\Models\Partenaire;

/**
 * Vérification d'une carte par un partenaire.
 *
 * Réponse binaire (utilisable / non valide) : une carte inconnue, expirée,
 * suspendue ou révoquée produit la même réponse, pour empêcher l'énumération
 * des numéros. Aucune donnée du titulaire n'est exposée à ce stade.
 */
class VerifierCarteService
{
    public function verifier(string $numeroCarte, Partenaire $partenaire): ?Carte
    {
        $carte = Carte::query()->where('numero_carte', $numeroCarte)->first();
        $valide = $carte?->estUtilisable() === true;

        JournaliserAudit::enregistrer('carte.verifiee', $carte, [
            'numero_carte' => $numeroCarte,
            'partenaire_id' => $partenaire->id,
            'valide' => $valide,
        ]);

        return $valide ? $carte : null;
    }
}
