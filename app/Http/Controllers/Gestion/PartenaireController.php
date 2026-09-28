<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Gestion\EnregistrerPartenaireAction;
use App\Enums\StatutPartenaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\EnregistrerPartenaireRequest;
use App\Http\Requests\Gestion\FiltrerPartenairesRequest;
use App\Models\Partenaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Partenaires : liste, fiche, création, modification (taux historisé) et
 * activation / désactivation. Aucune suppression : un partenaire est
 * référencé par ses transactions.
 */
class PartenaireController extends Controller
{
    public function index(FiltrerPartenairesRequest $request): View
    {
        return view('gestion.partenaires.index', [
            'filtres' => $request->validated(),
            'partenaires' => Partenaire::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function donnees(FiltrerPartenairesRequest $request): JsonResponse
    {
        $filtres = $request->validated();

        // select() AVANT withCount() : sinon les colonnes de comptage sont écrasées.
        $requete = Partenaire::query()->select('partenaires.*')->withCount(['operateurs', 'transactions'])
            ->when($filtres['partenaire_id'] ?? null, fn ($q, int|string $id) => $q->whereKey($id))
            ->when($filtres['statut'] ?? null, fn ($q, string $statut) => $q->where('statut', $statut));

        return DataTables::eloquent($requete)
            ->editColumn('taux_reduction', fn (Partenaire $p) => $p->tauxFormate())
            ->addColumn('statut_libelle', fn (Partenaire $p) => $p->statut->libelle())
            ->addColumn('lien', fn (Partenaire $p) => route('gestion.partenaires.show', $p))
            ->filter(function ($query) use ($request): void {
                $recherche = addcslashes(trim((string) $request->input('search.value')), '%_\\');

                if ($recherche !== '') {
                    $query->where(fn ($q) => $q
                        ->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('secteur', 'like', "%{$recherche}%")
                        ->orWhere('localisation', 'like', "%{$recherche}%"));
                }
            }, true)
            ->toJson();
    }

    public function create(): View
    {
        Gate::authorize('create', Partenaire::class);

        return view('gestion.partenaires.formulaire', ['partenaire' => new Partenaire]);
    }

    public function store(EnregistrerPartenaireRequest $request, EnregistrerPartenaireAction $enregistrer): RedirectResponse
    {
        $partenaire = $enregistrer->creer($request->validated(), $request->user());

        return redirect()->route('gestion.partenaires.show', $partenaire)
            ->with('succes', "Le partenaire {$partenaire->nom} a été créé. Ajoutez maintenant ses utilisateurs.");
    }

    public function show(Partenaire $partenaire): View
    {
        Gate::authorize('view', $partenaire);

        $partenaire->load([
            'operateurs' => fn ($q) => $q->with('roles')->orderBy('nom'),
            'historiqueTaux' => fn ($q) => $q->with('modifiePar.roles')->latest('modifie_le')->latest('id'),
        ])->loadCount('transactions');

        return view('gestion.partenaires.show', ['partenaire' => $partenaire]);
    }

    public function edit(Partenaire $partenaire): View
    {
        Gate::authorize('update', $partenaire);

        return view('gestion.partenaires.formulaire', ['partenaire' => $partenaire]);
    }

    public function update(EnregistrerPartenaireRequest $request, Partenaire $partenaire, EnregistrerPartenaireAction $enregistrer): RedirectResponse
    {
        $enregistrer->modifier($partenaire, $request->validated(), $request->user());

        return redirect()->route('gestion.partenaires.show', $partenaire)->with('succes', 'Le partenaire a été mis à jour.');
    }

    public function changerStatut(Request $request, Partenaire $partenaire, EnregistrerPartenaireAction $enregistrer): RedirectResponse
    {
        Gate::authorize('update', $partenaire);

        $statut = StatutPartenaire::from($request->validate([
            'statut' => ['required', 'in:'.StatutPartenaire::Actif->value.','.StatutPartenaire::Inactif->value],
        ])['statut']);

        $enregistrer->changerStatut($partenaire, $statut);

        return redirect()->route('gestion.partenaires.show', $partenaire)->with('succes', $statut === StatutPartenaire::Actif
            ? 'Le partenaire a été réactivé.'
            : 'Le partenaire a été désactivé : ses utilisateurs ne peuvent plus effectuer de transaction.');
    }
}
