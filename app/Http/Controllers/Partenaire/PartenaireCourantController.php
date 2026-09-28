<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\ChoisirPartenaireRequest;
use App\Models\Partenaire;
use App\Services\JournaliserAudit;
use App\Services\PartenaireCourant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin / superadmin : « Agir pour le compte de » un partenaire (option A).
 */
class PartenaireCourantController extends Controller
{
    public function store(ChoisirPartenaireRequest $request): RedirectResponse
    {
        $partenaire = Partenaire::query()->findOrFail($request->validated('partenaire_id'));

        PartenaireCourant::definir($partenaire);
        JournaliserAudit::enregistrer('partenaire.choisi', $partenaire);

        return redirect()->route('partenaire.tableau-de-bord')
            ->with('succes', "Vous agissez désormais pour le compte de {$partenaire->nom}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(PartenaireCourant::peutChoisir($request->user()), 403);

        PartenaireCourant::oublier();

        return redirect()->route('partenaire.tableau-de-bord');
    }
}
