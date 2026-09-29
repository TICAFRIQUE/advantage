<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Comptes\CreerCompteAction;
use App\Exceptions\OperationCompteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\CreerUtilisateurRequest;
use App\Http\Requests\Gestion\FiltrerUtilisateursRequest;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\Listes\ListeUtilisateurs;
use App\Services\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Paramètres › Utilisateurs : comptes du back-office (superadmin, admin,
 * agent). Les utilisateurs des partenaires se gèrent depuis la fiche du
 * partenaire. Actions sur un compte : CompteController (policy UserPolicy).
 */
class UtilisateurController extends Controller
{
    public function index(FiltrerUtilisateursRequest $request): View
    {
        return view('gestion.utilisateurs.index', [
            'filtres' => $request->validated(),
            'roles' => RoleUtilisateur::query()->deLEspace('gestion')->get()->sortBy(fn (RoleUtilisateur $role) => [$role->rang(), $role->libelle()]),
        ]);
    }

    public function donnees(FiltrerUtilisateursRequest $request): JsonResponse
    {
        $liste = $request->liste();

        return DataTables::eloquent($liste->requete())
            ->addColumn('identifiant', fn (User $u) => '@'.$u->nom_utilisateur)
            ->addColumn('role', fn (User $u) => $u->rolePrincipal()?->libelle() ?? '—')
            ->addColumn('etat', fn (User $u) => ListeUtilisateurs::etat($u))
            ->editColumn('derniere_connexion_le', fn (User $u) => $u->derniere_connexion_le?->format('d/m/Y H:i') ?? 'Jamais')
            ->addColumn('cree_par', fn (User $u) => $u->creePar?->libelleActeur() ?? 'Système')
            ->addColumn('lien', fn (User $u) => route('gestion.utilisateurs.show', $u))
            ->filter(function ($query) use ($request, $liste): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche !== '') {
                    $liste->rechercher($query, $recherche);
                }
            }, true)
            ->toJson();
    }

    public function create(Request $request): View
    {
        abort_if(GardeDroits::rolesGestionAttribuables($request->user()) === [], 403);

        return view('gestion.utilisateurs.creer', [
            'roles' => GardeDroits::rolesGestionAttribuables($request->user()),
            'pays' => Telephone::tousLesPays(),
        ]);
    }

    public function store(CreerUtilisateurRequest $request, CreerCompteAction $creer): RedirectResponse
    {
        try {
            ['compte' => $compte, 'pin' => $pin] = $creer($request->donnees(), $request->role(), $request->user());
        } catch (OperationCompteException $exception) {
            return back()->withInput()->withErrors(['nom_utilisateur' => $exception->getMessage()]);
        }

        return redirect()->route('gestion.utilisateurs.show', $compte)
            ->with('pin_genere', ['nom' => $compte->nom, 'nom_utilisateur' => $compte->nom_utilisateur, 'pin' => $pin]);
    }

    public function show(User $compte): View
    {
        abort_unless($compte->estDuBackOffice(), 404);

        $compte->load(['roles', 'creePar.roles', 'modifiePar.roles'])
            ->loadCount(['cartesActivees', 'transactionsValidees']);

        return view('gestion.utilisateurs.show', ['compte' => $compte, 'etat' => ListeUtilisateurs::etat($compte)]);
    }
}
