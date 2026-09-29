<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Gestion\ActiverCarteAction;
use App\Enums\Permission;
use App\Exceptions\ActivationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ActiverCarteRequest;
use App\Http\Requests\Gestion\FiltrerCartesRequest;
use App\Models\Carte;
use App\Services\JournaliserAudit;
use App\Services\Rapports\IndicateursCartes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CarteController extends Controller
{
    /**
     * Liste des cartes (toutes, tous agents confondus), paginée côté serveur.
     */
    public function index(FiltrerCartesRequest $request): View
    {
        $filtres = $request->validated();

        if (filled($filtres['recherche'] ?? null) || filled($filtres['statut'] ?? null)) {
            JournaliserAudit::enregistrer('cartes.recherchees', donnees: array_filter([
                'recherche' => $filtres['recherche'] ?? null,
                'statut' => $filtres['statut'] ?? null,
            ]));
        }

        $mesActivations = $request->boolean('mes_activations');

        $cartes = $request->liste()->requete()
            ->latest('active_le')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Indicateurs du parc (ou des seules activations de l'utilisateur),
        // indépendants de la recherche et du statut filtrés.
        $indicateurs = IndicateursCartes::calculer(
            Carte::query()->when($mesActivations, fn (Builder $query) => $query->where('active_par_id', $request->user()->id)),
        );

        return view('gestion.cartes.index', ['cartes' => $cartes, 'filtres' => $filtres, 'indicateurs' => $indicateurs]);
    }

    public function create(): View
    {
        Gate::authorize('create', Carte::class);

        return view('gestion.cartes.activer');
    }

    public function store(ActiverCarteRequest $request, ActiverCarteAction $activer): RedirectResponse
    {
        try {
            $carte = $activer($request->donnees(), $request->user());
        } catch (ActivationImpossibleException $exception) {
            return back()->withInput()->withErrors(['activation' => $exception->getMessage()]);
        }

        return redirect()->route('gestion.cartes.show', $carte)
            ->with('succes', "Carte {$carte->numeroFormate()} activée pour {$carte->titulaire->nomComplet()}.");
    }

    public function show(Carte $carte): View
    {
        Gate::authorize('view', $carte);

        JournaliserAudit::enregistrer('carte.consultee', $carte);

        $carte->load(['titulaire.cartes' => fn ($query) => $query->latest('active_le'), 'activePar.roles', 'modifiePar.roles', 'titulaire.creePar.roles']);

        // Historique permanent (le journal d'audit n'est conservé que 14 jours).
        $historique = $carte->operations()
            ->with('effectueePar.roles')
            ->latest('effectuee_le')
            ->latest('id')
            ->limit(10)
            ->get();

        // Passages chez les partenaires : réservés au droit « rapport des transactions ».
        $voirTransactions = request()->user()->can(Permission::VoirRapportTransactions->value);

        return view('gestion.cartes.show', [
            'carte' => $carte,
            'historique' => $historique,
            'nombreOperations' => $carte->operations()->count(),
            'transactions' => $voirTransactions
                ? $carte->transactions()->with(['partenaire', 'validePar.roles'])->latest('validee_le')->latest('id')->limit(10)->get()
                : null,
            'nombreTransactions' => $voirTransactions ? $carte->transactions()->count() : 0,
        ]);
    }
}
