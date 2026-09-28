<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Gestion\ChangerStatutCarteAction;
use App\Enums\StatutCarte;
use App\Exceptions\ActionCarteImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ChangerStatutCarteRequest;
use App\Models\Carte;
use Illuminate\Http\RedirectResponse;

class StatutCarteController extends Controller
{
    public function __invoke(ChangerStatutCarteRequest $request, Carte $carte, ChangerStatutCarteAction $changer): RedirectResponse
    {
        $cible = $request->statutCible();

        try {
            $changer($carte, $cible, $request->validated('motif'));
        } catch (ActionCarteImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        $message = match ($cible) {
            StatutCarte::Suspendue => 'suspendue',
            StatutCarte::Revoquee => 'révoquée définitivement',
            default => 'réactivée',
        };

        return redirect()->route('gestion.cartes.show', $carte)
            ->with('succes', "La carte {$carte->numeroFormate()} a été {$message}.");
    }
}
