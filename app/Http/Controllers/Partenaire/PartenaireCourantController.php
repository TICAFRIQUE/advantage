<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\ChoisirPartenaireRequest;
use App\Models\Partenaire;
use App\Services\JournaliserAudit;
use App\Services\PartenaireCourant;
use Illuminate\Http\RedirectResponse;

/**
 * Back-office : « Agir pour le compte de » un partenaire (option A).
 */
class PartenaireCourantController extends Controller
{
    public function store(ChoisirPartenaireRequest $request): RedirectResponse
    {
        $partenaire = Partenaire::query()->findOrFail($request->validated('partenaire_id'));

        PartenaireCourant::definir($partenaire);
        JournaliserAudit::enregistrer('partenaire.choisi', $partenaire);

        return redirect()->route('gestion.transaction.verifier')
            ->with('succes', "Vous agissez désormais pour le compte de {$partenaire->nom}.");
    }
}
