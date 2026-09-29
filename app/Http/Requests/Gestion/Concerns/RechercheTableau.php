<?php

namespace App\Http\Requests\Gestion\Concerns;

use Illuminate\Support\Str;

/**
 * Texte de la zone « Rechercher » du tableau, transmis à l'export
 * (paramètre recherche_tableau, borné à 100 caractères).
 */
trait RechercheTableau
{
    protected function rechercheTableau(): ?string
    {
        $recherche = Str::limit(trim((string) $this->input('recherche_tableau')), 100, '');

        return $recherche === '' ? null : $recherche;
    }
}
