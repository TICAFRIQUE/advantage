<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Comptes\CreerCompteAction;
use App\Enums\Role;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\CreerUtilisateurRequest;
use App\Http\Requests\Gestion\FiltrerUtilisateursRequest;
use App\Models\User;
use App\Services\Droits\GardeDroits;
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
        return view('gestion.utilisateurs.index', ['filtres' => $request->validated()]);
    }

    public function donnees(FiltrerUtilisateursRequest $request): JsonResponse
    {
        $filtres = $request->validated();

        $requete = User::query()->select('users.*')
            ->role(array_map(fn (Role $role) => $role->value, Role::roleGestion()))
            ->with(['roles', 'creePar.roles'])
            ->when($filtres['role'] ?? null, fn ($q, string $role) => $q->role($role))
            ->when($filtres['etat'] ?? null, fn ($q, string $etat) => match ($etat) {
                'verrouille' => $q->whereNotNull('users.verrouille_le'),
                'inactif' => $q->where('users.statut', StatutUtilisateur::Inactif),
                default => $q->where('users.statut', StatutUtilisateur::Actif)->whereNull('users.verrouille_le'),
            });

        return DataTables::eloquent($requete)
            ->addColumn('identifiant', fn (User $u) => '@'.$u->nom_utilisateur)
            ->addColumn('role', fn (User $u) => $u->rolePrincipal()?->libelle() ?? '—')
            ->addColumn('etat', fn (User $u) => $this->etat($u))
            ->editColumn('derniere_connexion_le', fn (User $u) => $u->derniere_connexion_le?->format('d/m/Y H:i') ?? 'Jamais')
            ->addColumn('cree_par', fn (User $u) => $u->creePar?->libelleActeur() ?? 'Système')
            ->addColumn('lien', fn (User $u) => route('gestion.utilisateurs.show', $u))
            ->filter(function ($query) use ($request): void {
                $recherche = addcslashes(trim((string) $request->input('search.value')), '%_\\');

                if ($recherche !== '') {
                    $query->where(fn ($q) => $q
                        ->where('users.nom', 'like', "%{$recherche}%")
                        ->orWhere('users.nom_utilisateur', 'like', "%{$recherche}%"));
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
        abort_unless($compte->hasAnyRole(Role::roleGestion()), 404);

        $compte->load(['roles', 'creePar.roles', 'modifiePar.roles'])
            ->loadCount(['cartesActivees', 'transactionsValidees']);

        return view('gestion.utilisateurs.show', ['compte' => $compte, 'etat' => $this->etat($compte)]);
    }

    private function etat(User $compte): string
    {
        return match (true) {
            $compte->estVerrouille() => 'Verrouillé',
            $compte->statut !== StatutUtilisateur::Actif => 'Désactivé',
            default => 'Actif',
        };
    }
}
