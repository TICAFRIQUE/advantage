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
            'parPartenaire' => $rapport->parPartenaire(),
            'partenaires' => Partenaire::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function donnees(FiltrerRapportTransactionsRequest $request): JsonResponse
    {
        $peutVoirCartes = $request->user()->can(Permission::VoirCartes->value);

        $requete = $request->rapport()->requete()
            ->with(['partenaire', 'carte.titulaire', 'validePar.roles'])
            ->select('transactions.*');

        return DataTables::eloquent($requete)
            ->editColumn('validee_le', fn (Transaction $t) => $t->validee_le->format('d/m/Y H:i'))
            ->addColumn('partenaire', fn (Transaction $t) => $t->partenaire->nom)
            ->addColumn('carte', fn (Transaction $t) => $t->carte->numeroFormate())
            ->addColumn('titulaire', fn (Transaction $t) => $t->carte->titulaire->nomComplet())
            ->editColumn('taux_applique', fn (Transaction $t) => rtrim(rtrim((string) $t->taux_applique, '0'), '.').' %')
            ->addColumn('valide_par', fn (Transaction $t) => $t->validePar?->libelleActeur() ?? '—')
            ->addColumn('lien_carte', fn (Transaction $t) => $peutVoirCartes ? route('gestion.cartes.show', $t->carte_id) : null)
            ->filter(function ($query) use ($request): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche === '') {
                    return;
                }

                $texte = addcslashes($recherche, '%_\\');
                $chiffres = preg_replace('/\D/', '', $recherche);

                // Chaque niveau est regroupé entre parenthèses : un OR non groupé dans
                // un whereHas « fuirait » hors de la condition de jointure.
                $query->where(fn ($q) => $q
                    ->whereHas('partenaire', fn ($p) => $p->where('nom', 'like', "%{$texte}%"))
                    ->orWhereHas('carte', fn ($c) => $c->where(fn ($c) => $c
                        ->when($chiffres !== '', fn ($c) => $c->where('numero_carte', 'like', $chiffres.'%'))
                        ->orWhereHas('titulaire', fn ($t) => $t->where(fn ($t) => $t
                            ->where('nom', 'like', "%{$texte}%")
                            ->orWhere('prenom', 'like', "%{$texte}%"))))));
            }, true)
            ->toJson();
    }
}
