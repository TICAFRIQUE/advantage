<?php

namespace App\Http\Controllers\Partenaire;

use App\Enums\FormatExport;
use App\Exceptions\ExportTropVolumineuxException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\FiltrerHistoriqueRequest;
use App\Models\Transaction;
use App\Services\Exports\Exporteur;
use App\Services\PartenaireCourant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class TransactionController extends Controller
{
    /**
     * Étape 4 : remise accordée. L'identité du titulaire n'est révélée
     * qu'ici, après validation du code.
     */
    public function show(Transaction $transaction): View
    {
        Gate::authorize('view', $transaction);

        $transaction->load(['carte.titulaire', 'validePar.roles', 'partenaire']);

        return view('partenaire.transactions.show', ['transaction' => $transaction]);
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Transaction::class);

        return view('partenaire.transactions.index', [
            'partenaire' => PartenaireCourant::pour(auth()->user()),
        ]);
    }

    /**
     * Données de l'historique (Yajra, traitement côté serveur), limitées au
     * partenaire courant. Les valeurs sont échappées par Yajra.
     */
    public function donnees(FiltrerHistoriqueRequest $request): JsonResponse
    {
        $liste = $request->liste();

        return DataTables::eloquent($liste->requete())
            ->editColumn('validee_le', fn (Transaction $t) => $t->validee_le->format('d/m/Y H:i'))
            ->addColumn('carte', fn (Transaction $t) => $t->carte->numeroFormate())
            ->addColumn('titulaire', fn (Transaction $t) => $t->carte->titulaire->nomComplet())
            ->editColumn('taux_applique', fn (Transaction $t) => rtrim(rtrim((string) $t->taux_applique, '0'), '.').' %')
            ->addColumn('operateur', fn (Transaction $t) => $t->validePar?->libelleActeur() ?? '—')
            ->filter(function ($query) use ($request, $liste): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche !== '') {
                    $liste->rechercher($query, $recherche);
                }
            }, true)
            ->toJson();
    }

    /**
     * Export de l'historique (mêmes filtres et même recherche que l'écran),
     * limité au partenaire courant et journalisé par l'exporteur.
     */
    public function exporter(FiltrerHistoriqueRequest $request, FormatExport $format, Exporteur $exporteur): Response
    {
        try {
            return $exporteur->telecharger($request->liste(), $format, $request->user());
        } catch (ExportTropVolumineuxException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }
    }
}
