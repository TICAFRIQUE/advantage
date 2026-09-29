<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Commandes de mise en production, lues depuis docs/production.md : une seule
 * source, versionnée avec le code et consultable ici par le superadmin.
 */
class MiseEnProductionController extends Controller
{
    public function show(): View
    {
        $contenu = Str::markdown((string) file_get_contents(base_path('docs/production.md')), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return view('gestion.mise-en-production', ['contenu' => $contenu]);
    }
}
