<?php

namespace App\View\Components;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Gestion\SmsSimulesController;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Navigation latérale : un seul endroit décrit le menu de chaque espace ;
 * une entrée n'apparaît que si l'utilisateur détient la permission (la route
 * reste de toute façon protégée par permission + policy côté serveur).
 */
class BarreLaterale extends Component
{
    /**
     * @var list<array{titre: ?string, entrees: list<array{libelle: string, icone: string, url: string, actif: bool}>}>
     */
    public array $sections;

    public function __construct(public bool $reduite = false)
    {
        $user = auth()->user();

        $this->sections = $this->filtrer($user->hasRole(Role::Partenaire)
            ? $this->menuPartenaire($user)
            : $this->menuGestion($user));
    }

    public function render(): View|Closure|string
    {
        return view('components.barre-laterale');
    }

    /**
     * @return array<string, list<array<string, mixed>|null>>
     */
    private function menuGestion(User $user): array
    {
        return [
            '' => [
                $this->entree('Tableau de bord', 'bi-speedometer2', 'gestion.tableau-de-bord', ['gestion.tableau-de-bord'], $user->can(Permission::VoirTableauDeBord->value)),
            ],
            'Cartes' => [
                $this->entree('Activer une carte', 'bi-credit-card-2-front', 'gestion.cartes.create', ['gestion.cartes.create'], $user->can(Permission::ActiverCarte->value)),
                $this->entree('Liste des cartes', 'bi-wallet2', 'gestion.cartes.index', ['gestion.cartes.index', 'gestion.cartes.show', 'gestion.cartes.titulaire.*'], $user->can(Permission::VoirCartes->value)),
                $this->entree('Rapport des cartes', 'bi-bar-chart-line', 'gestion.cartes.rapport', ['gestion.cartes.rapport'], $user->can(Permission::VoirRapportCartes->value)),
            ],
            'Partenaires' => [
                $this->entree('Liste des partenaires', 'bi-shop', 'gestion.partenaires.index', ['gestion.partenaires.*'], $user->can(Permission::VoirPartenaires->value)),
                $this->entree('Transaction', 'bi-upc-scan', 'gestion.transaction.nouvelle', ['gestion.transaction.*'], $user->can(Permission::EffectuerTransactionPartenaire->value)),
                $this->entree('Rapport des transactions', 'bi-graph-up', 'gestion.transactions.rapport', ['gestion.transactions.rapport'], $user->can(Permission::VoirRapportTransactions->value)),
            ],
            'Outils de test' => [
                $this->entree('SMS simulés', 'bi-chat-dots', 'gestion.sms-simules.index', ['gestion.sms-simules.*'],
                    SmsSimulesController::disponible() && $user->can(Permission::VoirSmsSimules->value)),
            ],
            // Réservé au superadmin (même double condition que les routes).
            'Système' => [
                $this->entree('Éléments supprimés', 'bi-archive', 'gestion.corbeille.index', ['gestion.corbeille.*'],
                    $user->hasRole(Role::Superadmin) && $user->can(Permission::RestaurerElements->value)),
                $this->entree('Mise en production', 'bi-rocket-takeoff', 'gestion.mise-en-production', ['gestion.mise-en-production'],
                    $user->hasRole(Role::Superadmin) && $user->can(Permission::VoirCommandesProduction->value)),
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>|null>>
     */
    private function menuPartenaire(User $user): array
    {
        return [
            '' => [
                $this->entree('Tableau de bord', 'bi-speedometer2', 'partenaire.tableau-de-bord', ['partenaire.tableau-de-bord'], $user->can(Permission::AccederEspacePartenaire->value)),
                $this->entree('Transaction', 'bi-upc-scan', 'partenaire.transaction.verifier', ['partenaire.transaction.*'], $user->can(Permission::EffectuerTransaction->value)),
                $this->entree('Historique', 'bi-clock-history', 'partenaire.historique.index', ['partenaire.historique.*'], $user->can(Permission::VoirHistoriqueTransactions->value)),
            ],
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>|null>>  $definition
     * @return list<array{titre: ?string, entrees: list<array{libelle: string, icone: string, url: string, actif: bool}>}>
     */
    private function filtrer(array $definition): array
    {
        $sections = [];

        foreach ($definition as $titre => $entrees) {
            $visibles = array_values(array_filter($entrees));

            if ($visibles !== []) {
                $sections[] = ['titre' => $titre === '' ? null : $titre, 'entrees' => $visibles];
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
