<?php

namespace App\Http\Requests\Gestion\Concerns;

use App\Services\Listes\Liste;

/**
 * Form Request de filtres d'une liste exportable : fournit la liste filtrée
 * (même définition pour l'écran et l'export).
 */
interface DefinitListe
{
    public function liste(): Liste;
}
