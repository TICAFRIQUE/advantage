<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PartenaireCourant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord de l'espace partenaire (opérateurs uniquement).
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): View
    {
        $partenaire = PartenaireCourant::pour($request->user());
        $indicateurs = null;

        if ($partenaire !== null) {
            $resultat = Transaction::query()
                ->where('partenaire_id', $partenaire->id)
                ->selectRaw('SUM(validee_le >= ?) AS aujourd_hui', [today()])
                ->selectRaw('SUM(validee_le >= ?) AS ce_mois', [today()->startOfMonth()])
                ->first();

            $indicateurs = [
                'aujourd_hui' => (int) $resultat->aujourd_hui,
                'ce_mois' => (int) $resultat->ce_mois,
            ];
        }

        return view('partenaire.tableau-de-bord', ['partenaire' => $partenaire, 'indicateurs' => $indicateurs]);
    }
}
