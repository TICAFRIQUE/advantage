<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Renseigne automatiquement l'auteur de la création et de la dernière
 * modification à partir de l'utilisateur connecté.
 *
 * Le modèle déclare les colonnes via les constantes COLONNE_CREATEUR et
 * COLONNE_MODIFICATEUR (null pour désactiver l'une ou l'autre).
 */
trait TraceAuteurs
{
    protected static function bootTraceAuteurs(): void
    {
        static::creating(function (Model $model): void {
            $colonne = static::COLONNE_CREATEUR;

            if ($colonne !== null && $model->getAttribute($colonne) === null && Auth::id() !== null) {
                $model->setAttribute($colonne, Auth::id());
            }
        });

        static::updating(function (Model $model): void {
            if (static::COLONNE_MODIFICATEUR !== null && Auth::id() !== null) {
                $model->setAttribute(static::COLONNE_MODIFICATEUR, Auth::id());
            }
        });
    }
}
