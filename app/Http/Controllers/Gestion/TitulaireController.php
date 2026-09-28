<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Gestion\ModifierTitulaireAction;
use App\Exceptions\ActionCarteImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ModifierIdentiteTitulaireRequest;
use App\Http\Requests\Gestion\ModifierTelephoneTitulaireRequest;
use App\Models\Carte;
use App\Services\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modification de la fiche du titulaire depuis sa carte. Le téléphone a sa
 * propre page, protégée par une confirmation récente du PIN (5 min).
 */
class TitulaireController extends Controller
{
    public function edit(Request $request, Carte $carte): View
    {
        abort_unless($request->user()->canAny(['modifierTitulaire', 'modifierTelephone'], $carte), 403);

        return view('gestion.cartes.titulaire', ['carte' => $carte->load('titulaire')]);
    }

    public function update(ModifierIdentiteTitulaireRequest $request, Carte $carte, ModifierTitulaireAction $modifier): RedirectResponse
    {
        $modifier->identite($carte->titulaire, $request->validated());

        return redirect()->route('gestion.cartes.show', $carte)->with('succes', 'Les informations du titulaire ont été mises à jour.');
    }

    public function editTelephone(Request $request, Carte $carte): View
    {
        abort_unless($request->user()->can('modifierTelephone', $carte), 403);

        return view('gestion.cartes.telephone', [
            'carte' => $carte->load('titulaire'),
            'pays' => Telephone::tousLesPays(),
        ]);
    }

    public function updateTelephone(ModifierTelephoneTitulaireRequest $request, Carte $carte, ModifierTitulaireAction $modifier): RedirectResponse
    {
        try {
            $modifier->telephone($carte->titulaire, $request->telephone());
        } catch (ActionCarteImpossibleException $exception) {
            return back()->withInput()->withErrors(['telephone' => $exception->getMessage()]);
        }

        return redirect()->route('gestion.cartes.show', $carte)
            ->with('succes', 'Le téléphone a été modifié. L\'ancien numéro a été informé par SMS.');
    }
}
