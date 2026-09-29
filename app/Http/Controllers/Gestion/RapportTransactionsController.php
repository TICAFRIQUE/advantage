<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\FiltrerRapportTransactionsRequest;
use App\Models\Partenaire;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Rapport des transactions : indicateurs + liste filtrable (Yajra).
 */
class RapportTransactionsController extends Controller
{
    public function index(FiltrerRapportTransactionsRequest $request): View
    {
        $rapport = $request->rapport();

        return view('gestion.transactions.rapport', [
            'filtres' => $request->validated(),
            'indicateurs' => $rapport->indicateurs(),
            // Partenaires supprimés compris : leurs transactions restent consultables.
            'partenaires' => Partenaire::withTrashed()->orderBy('nom')->get(['id', 'nom', 'deleted_at']),
        ]);
    }

    public function donnees(FiltrerRapportTransactionsRequest $request): JsonResponse
    {
        $peutVoirCartes = $request->user()->can(Permission::VoirCartes->value);

        $liste = $request->liste();

        return DataTables::eloquent($liste->requete())
            ->editColumn('validee_le', fn (Transaction $t) => $t->validee_le->format('d/m/Y H:i'))
            ->addColumn('partenaire', fn (Transaction $t) => $t->partenaire->nom)
            ->addColumn('carte', fn (Transaction $t) => $t->carte->numeroFormate())
            ->addColumn('titulaire', fn (Transaction $t) => $t->carte->titulaire->nomComplet())
            ->editColumn('taux_applique', fn (Transaction $t) => rtrim(rtrim((string) $t->taux_applique, '0'), '.').' %')
            ->addColumn('valide_par', fn (Transaction $t) => $t->validePar?->libelleActeur() ?? '—')
            ->addColumn('lien_carte', fn (Transaction $t) => $peutVoirCartes ? route('gestion.cartes.show', $t->carte_id) : null)
            ->filter(function ($query) use ($request, $liste): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche !== '') {
                    $liste->rechercher($query, $recherche);
                }
            }, true)
            ->toJson();
    }
}
