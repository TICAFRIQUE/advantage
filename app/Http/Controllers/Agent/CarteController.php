<?php

namespace App\Http\Controllers\Agent;

use App\Actions\Agent\ActiverCarteAction;
use App\Actions\Agent\DeclarerPerteAction;
use App\Enums\StatutCarte;
use App\Exceptions\ActionCarteImpossibleException;
use App\Exceptions\ActivationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ActiverCarteRequest;
use App\Http\Requests\Agent\DeclarerPerteRequest;
use App\Http\Requests\Agent\FiltrerCartesRequest;
use App\Models\Carte;
use App\Models\JournalAudit;
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

        $cartes = Carte::query()
            ->with(['titulaire', 'activePar.roles', 'modifiePar.roles'])
            ->when($filtres['recherche'] ?? null, fn (Builder $query, string $recherche) => $this->rechercher($query, $recherche))
            ->when($filtres['statut'] ?? null, fn (Builder $query, string $statut) => $this->filtrerStatut($query, StatutCarte::from($statut)))
            ->when($request->boolean('mes_activations'), fn (Builder $query) => $query->where('active_par_id', $request->user()->id))
            ->latest('active_le')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('agent.cartes.index', ['cartes' => $cartes, 'filtres' => $filtres]);
    }

    public function create(): View
    {
        Gate::authorize('create', Carte::class);

        return view('agent.cartes.activer');
    }

    public function store(ActiverCarteRequest $request, ActiverCarteAction $activer): RedirectResponse
    {
        try {
            $carte = $activer($request->donnees(), $request->user());
        } catch (ActivationImpossibleException $exception) {
            return back()->withInput()->withErrors(['activation' => $exception->getMessage()]);
        }

        return redirect()->route('agent.cartes.show', $carte)
            ->with('succes', "Carte {$carte->numeroFormate()} activée pour {$carte->titulaire->nomComplet()}.");
    }

    public function show(Carte $carte): View
    {
        Gate::authorize('view', $carte);

        $carte->load(['titulaire.cartes' => fn ($query) => $query->latest('active_le'), 'activePar.roles', 'modifiePar.roles', 'titulaire.creePar.roles']);

        $historique = JournalAudit::query()
            ->with('acteur.roles')
            ->where('type_entite', 'Carte')
            ->where('entite_id', $carte->id)
            ->latest('cree_le')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('agent.cartes.show', ['carte' => $carte, 'historique' => $historique]);
    }

    public function declarerPerte(DeclarerPerteRequest $request, Carte $carte, DeclarerPerteAction $declarer): RedirectResponse
    {
        try {
            $declarer($carte, $request->validated('motif'));
        } catch (ActionCarteImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('agent.cartes.show', $carte)
            ->with('succes', "La carte {$carte->numeroFormate()} a été déclarée perdue et révoquée.");
    }

    /**
     * Numérique : préfixe du numéro de carte ou fragment du téléphone.
     * Texte : nom ou prénoms du titulaire.
     *
     * @param  Builder<Carte>  $query
     */
    private function rechercher(Builder $query, string $recherche): void
    {
        $chiffres = preg_replace('/\D/', '', $recherche);
        $texte = addcslashes(trim($recherche), '%_\\');

        if ($chiffres !== '' && $chiffres === preg_replace('/\s/', '', $recherche)) {
            $query->where(fn (Builder $q) => $q
                ->where('numero_carte', 'like', $chiffres.'%')
                ->orWhereHas('titulaire', fn (Builder $t) => $t->where('telephone', 'like', '%'.$chiffres.'%')));

            return;
        }

        $query->whereHas('titulaire', fn (Builder $t) => $t
            ->where('nom', 'like', '%'.$texte.'%')
            ->orWhere('prenom', 'like', '%'.$texte.'%'));
    }

    /**
     * Le filtre tient compte du statut effectif (date échue = expirée).
     *
     * @param  Builder<Carte>  $query
     */
    private function filtrerStatut(Builder $query, StatutCarte $statut): void
    {
        match ($statut) {
            StatutCarte::Active => $query->where('statut', StatutCarte::Active)->where('expire_le', '>', now()),
            StatutCarte::Expiree => $query->where(fn (Builder $q) => $q
                ->where('statut', StatutCarte::Expiree)
                ->orWhere(fn (Builder $q) => $q->where('statut', StatutCarte::Active)->where('expire_le', '<=', now()))),
            default => $query->where('statut', $statut),
        };
    }
}
