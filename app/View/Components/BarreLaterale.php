<?php

namespace App\View\Components;

use App\Enums\Permission;
use App\Http\Controllers\Admin\SmsSimulesController;
use App\Models\Carte;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * Navigation latérale : un seul endroit décrit le menu, chaque entrée
 * n'apparaît que si l'utilisateur y est autorisé (la route reste de toute
 * façon protégée par permission + policy côté serveur).
 */
class BarreLaterale extends Component
{
    /**
     * @var list<array{titre: string, entrees: list<array{libelle: string, icone: string, url: string, actif: bool}>}>
     */
    public array $sections;

    public function __construct(public bool $reduite = false)
    {
        $this->sections = $this->construireSections(auth()->user());
    }

    public function render(): View|Closure|string
    {
        return view('components.barre-laterale');
    }

    /**
     * @return list<array{titre: string, entrees: list<array{libelle: string, icone: string, url: string, actif: bool}>}>
     */
    private function construireSections(User $user): array
    {
        $definition = [
            'Pilotage' => [
                $this->entree('Tableau de bord', 'bi-speedometer2', 'admin.tableau-de-bord', ['admin.tableau-de-bord'], $user->can(Permission::AccederEspaceAdmin->value)),
            ],
            'Cartes' => [
                $this->entree('Accueil agent', 'bi-house-door', 'agent.tableau-de-bord', ['agent.tableau-de-bord'], $user->can(Permission::AccederEspaceAgent->value)),
                $this->entree('Activer une carte', 'bi-credit-card-2-front', 'agent.cartes.create', ['agent.cartes.create'], Gate::forUser($user)->allows('create', Carte::class)),
                $this->entree('Toutes les cartes', 'bi-wallet2', 'agent.cartes.index', ['agent.cartes.index', 'agent.cartes.show'], Gate::forUser($user)->allows('viewAny', Carte::class)),
            ],
            'Partenaire' => [
                $this->entree('Accueil partenaire', 'bi-shop', 'partenaire.tableau-de-bord', ['partenaire.tableau-de-bord'], $user->can(Permission::AccederEspacePartenaire->value)),
                $this->entree('Vérifier une carte', 'bi-upc-scan', 'partenaire.verifier', ['partenaire.verifier', 'partenaire.codes.*'], $user->can(Permission::VerifierCarte->value)),
                $this->entree('Historique des passages', 'bi-clock-history', 'partenaire.transactions.index', ['partenaire.transactions.*'], $user->can(Permission::VoirSesTransactions->value)),
            ],
            'Outils de test' => [
                $this->entree('SMS simulés', 'bi-chat-dots', 'admin.sms-simules.index', ['admin.sms-simules.*'],
                    SmsSimulesController::disponible() && $user->can(Permission::VoirSmsSimules->value)),
            ],
        ];

        $sections = [];

        foreach ($definition as $titre => $entrees) {
            $visibles = array_values(array_filter($entrees));

            if ($visibles !== []) {
                $sections[] = ['titre' => $titre, 'entrees' => $visibles];
            }
        }

        return $sections;
    }

    /**
     * @param  list<string>  $routesActives
     * @return array{libelle: string, icone: string, url: string, actif: bool}|null
     */
    private function entree(string $libelle, string $icone, string $route, array $routesActives, bool $autorise): ?array
    {
        if (! $autorise) {
            return null;
        }

        return [
            'libelle' => $libelle,
            'icone' => $icone,
            'url' => route($route),
            'actif' => request()->routeIs(...$routesActives),
        ];
    }
}
