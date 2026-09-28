<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PartenaireCourant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        $periode = $request->validate([
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);

        $requete = Transaction::query()
            ->with(['carte.titulaire', 'validePar.roles'])
            ->where('partenaire_id', PartenaireCourant::pour($request->user())->id)
            ->when($periode['du'] ?? null, fn (Builder $q, string $du) => $q->where('validee_le', '>=', $du.' 00:00:00'))
            ->when($periode['au'] ?? null, fn (Builder $q, string $au) => $q->where('validee_le', '<=', $au.' 23:59:59'))
            ->select('transactions.*');

        return DataTables::eloquent($requete)
            ->editColumn('validee_le', fn (Transaction $t) => $t->validee_le->format('d/m/Y H:i'))
            ->addColumn('carte', fn (Transaction $t) => $t->carte->numeroFormate())
            ->addColumn('titulaire', fn (Transaction $t) => $t->carte->titulaire->nomComplet())
            ->editColumn('taux_applique', fn (Transaction $t) => rtrim(rtrim((string) $t->taux_applique, '0'), '.').' %')
            ->addColumn('operateur', fn (Transaction $t) => $t->validePar?->libelleActeur() ?? '—')
            ->filter(function (Builder $query) use ($request): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche === '') {
                    return;
                }

                $texte = addcslashes($recherche, '%_\\');
                $query->whereHas('carte', fn (Builder $c) => $c
                    ->where('numero_carte', 'like', preg_replace('/\s+/', '', $texte).'%')
                    ->orWhereHas('titulaire', fn (Builder $t) => $t
                        ->where('nom', 'like', "%{$texte}%")
                        ->orWhere('prenom', 'like', "%{$texte}%")));
            }, true)
            ->toJson();
    }
}
