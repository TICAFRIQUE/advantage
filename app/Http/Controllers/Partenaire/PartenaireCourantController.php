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
    /**
     * Point d'entrée d'une NOUVELLE transaction : le partenaire choisi pour la
     * précédente est oublié et doit être choisi à nouveau (évite d'enregistrer
     * une remise chez le mauvais partenaire par inadvertance).
     */
    public function nouvelle(): RedirectResponse
    {
        PartenaireCourant::oublier();

        return redirect()->route('gestion.transaction.verifier');
    }

    public function store(ChoisirPartenaireRequest $request): RedirectResponse
    {
        $partenaire = Partenaire::query()->findOrFail($request->validated('partenaire_id'));

        PartenaireCourant::definir($partenaire);
        JournaliserAudit::enregistrer('partenaire.choisi', $partenaire, ['partenaire' => $partenaire->nom]);

        return redirect()->route('gestion.transaction.verifier')
            ->with('succes', "Vous agissez désormais pour le compte de {$partenaire->nom}.");
    }
}
