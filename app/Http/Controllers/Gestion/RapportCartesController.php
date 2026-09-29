<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\FiltrerRapportCartesRequest;
use App\Models\OperationCarte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Rapport des cartes : historique des opérations sur la période choisie
 * (liste filtrable Yajra, côté serveur). Les indicateurs par type restent
 * calculables (RapportCartes::indicateurs) mais ne sont plus affichés.
 */
class RapportCartesController extends Controller
{
    public function index(FiltrerRapportCartesRequest $request): View
    {
        return view('gestion.cartes.rapport', [
            'filtres' => $request->validated() + ['mes_operations' => $request->boolean('mes_operations')],
            'agents' => User::query()->whereHas('roles', fn ($q) => $q->where('espace', 'gestion'))->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function donnees(FiltrerRapportCartesRequest $request): JsonResponse
    {
        $requete = $request->rapport()->requete()
            ->with(['carte.titulaire', 'effectueePar.roles'])
            ->select('operations_cartes.*');

        return DataTables::eloquent($requete)
            ->editColumn('effectuee_le', fn (OperationCarte $o) => $o->effectuee_le->format('d/m/Y H:i'))
            ->addColumn('operation', fn (OperationCarte $o) => $o->type->libelle())
            ->addColumn('numero', fn (OperationCarte $o) => $o->carte->numeroFormate())
            ->addColumn('titulaire', fn (OperationCarte $o) => $o->carte->titulaire->nomComplet())
            ->addColumn('telephone', fn (OperationCarte $o) => $o->carte->titulaire->telephoneFormate())
            ->addColumn('effectuee_par', fn (OperationCarte $o) => $o->libelleAuteur())
            ->editColumn('motif', fn (OperationCarte $o) => $o->motif ?? '—')
            ->addColumn('lien', fn (OperationCarte $o) => route('gestion.cartes.show', $o->carte_id))
            ->filter(function ($query) use ($request): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche === '') {
                    return;
                }

                $texte = addcslashes($recherche, '%_\\');
                $chiffres = preg_replace('/\D/', '', $recherche);

                $query->whereHas('carte', fn ($c) => $c->withTrashed()->where(fn ($c) => $c
                    ->when($chiffres !== '', fn ($c) => $c->where('numero_carte', 'like', $chiffres.'%'))
                    ->orWhereHas('titulaire', fn ($t) => $t
                        ->where('nom', 'like', "%{$texte}%")
                        ->orWhere('prenom', 'like', "%{$texte}%")
                        // Sans chiffre, un LIKE '%%' ramènerait toutes les cartes.
                        ->when($chiffres !== '', fn ($t) => $t->orWhere('telephone', 'like', "%{$chiffres}%")))));
            }, true)
            ->toJson();
    }
}
