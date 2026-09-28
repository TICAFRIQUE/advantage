<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\FiltrerRapportCartesRequest;
use App\Models\Carte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Rapport des cartes : indicateurs + liste filtrable (Yajra, côté serveur).
 */
class RapportCartesController extends Controller
{
    public function index(FiltrerRapportCartesRequest $request): View
    {
        $rapport = $request->rapport();

        return view('gestion.cartes.rapport', [
            'filtres' => $request->validated() + ['mes_activations' => $request->boolean('mes_activations')],
            'indicateurs' => $rapport->indicateurs(),
            'parAgent' => $rapport->parAgent(),
            'agents' => User::query()->role([Role::Admin->value, Role::Agent->value, Role::Superadmin->value])->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function donnees(FiltrerRapportCartesRequest $request): JsonResponse
    {
        $requete = $request->rapport()->requete()
            ->with(['titulaire', 'activePar.roles'])
            ->select('cartes.*');

        return DataTables::eloquent($requete)
            ->addColumn('numero', fn (Carte $c) => $c->numeroFormate())
            ->addColumn('titulaire', fn (Carte $c) => $c->titulaire->nomComplet())
            ->addColumn('telephone', fn (Carte $c) => $c->titulaire->telephoneFormate())
            ->addColumn('statut_libelle', fn (Carte $c) => $c->statutEffectif()->libelle())
            ->editColumn('active_le', fn (Carte $c) => $c->active_le?->format('d/m/Y H:i'))
            ->editColumn('expire_le', fn (Carte $c) => $c->expire_le?->format('d/m/Y'))
            ->addColumn('active_par', fn (Carte $c) => $c->activePar?->libelleActeur() ?? '—')
            ->addColumn('lien', fn (Carte $c) => route('gestion.cartes.show', $c))
            ->filter(function ($query) use ($request): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche === '') {
                    return;
                }

                $texte = addcslashes($recherche, '%_\\');
                $chiffres = preg_replace('/\D/', '', $recherche);

                $query->where(fn ($q) => $q
                    ->when($chiffres !== '', fn ($q) => $q->where('numero_carte', 'like', $chiffres.'%'))
                    ->orWhereHas('titulaire', fn ($t) => $t
                        ->where('nom', 'like', "%{$texte}%")
                        ->orWhere('prenom', 'like', "%{$texte}%")
                        // Sans chiffre, un LIKE '%%' ramènerait toutes les cartes.
                        ->when($chiffres !== '', fn ($t) => $t->orWhere('telephone', 'like', "%{$chiffres}%"))));
            }, true)
            ->toJson();
    }
}
