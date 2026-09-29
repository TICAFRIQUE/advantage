<?php

namespace App\View\Components;

use App\Enums\Permission;
use App\Enums\Role;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Menu du bas (smartphone et tablette), en plus du menu latéral : les
 * actions les plus fréquentes au pouce, l'action principale au centre.
 *
 * - Back-office : Accueil · Cartes · [Activer] · Transaction · Menu
 * - Partenaire : Accueil · Historique · [Transaction] · Profil
 *
 * Chaque entrée n'apparaît que si l'utilisateur détient la permission ; les
 * places libres sont complétées selon ses droits (partenaires, rapports).
 */
class MenuBas extends Component
{
    /**
     * @var list<array{libelle: string, icone: string, url: ?string, actif: bool, principal: bool, menu: bool}>
     */
    public array $entrees = [];

    public function __construct()
    {
        $user = auth()->user();

        $this->entrees = $user?->hasRole(Role::Partenaire) ? $this->partenaire() : $this->gestion();
    }

    public function shouldRender(): bool
    {
        return count($this->entrees) >= 2;
    }

    public function render(): View|Closure|string
    {
        return view('components.menu-bas');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gestion(): array
    {
        $user = auth()->user();
        $peut = fn (Permission $p) => $user->can($p->value);

        $principale = match (true) {
            $peut(Permission::ActiverCarte) => $this->entree('Activer', 'bi-plus-lg', 'gestion.cartes.create', ['gestion.cartes.create'], principal: true),
            $peut(Permission::EffectuerTransactionPartenaire) => $this->entree('Transaction', 'bi-upc-scan', 'gestion.transaction.nouvelle', ['gestion.transaction.*'], principal: true),
            default => null,
        };

        // Entrées candidates par ordre de priorité (hors action principale).
        $candidates = array_values(array_filter([
            $peut(Permission::VoirTableauDeBord) ? $this->entree('Accueil', 'bi-house', 'gestion.tableau-de-bord', ['gestion.tableau-de-bord']) : null,
            $peut(Permission::VoirCartes) ? $this->entree('Cartes', 'bi-wallet2', 'gestion.cartes.index', ['gestion.cartes.index', 'gestion.cartes.show', 'gestion.cartes.titulaire.*']) : null,
            $peut(Permission::EffectuerTransactionPartenaire) && $principale['libelle'] !== 'Transaction'
                ? $this->entree('Transaction', 'bi-upc-scan', 'gestion.transaction.nouvelle', ['gestion.transaction.*']) : null,
            $peut(Permission::VoirPartenaires) ? $this->entree('Partenaires', 'bi-shop', 'gestion.partenaires.index', ['gestion.partenaires.*']) : null,
            $peut(Permission::VoirRapportTransactions) ? $this->entree('Rapports', 'bi-graph-up', 'gestion.transactions.rapport', ['gestion.transactions.rapport']) : null,
        ]));

        $gauche = array_slice($candidates, 0, 2);
        $droite = array_slice($candidates, 2, 1);
        $menu = ['libelle' => 'Menu', 'icone' => 'bi-list', 'url' => null, 'actif' => false, 'principal' => false, 'menu' => true];

        return array_values(array_filter([...$gauche, $principale, ...$droite, $menu]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partenaire(): array
    {
        $user = auth()->user();

        return array_values(array_filter([
            $user->can(Permission::AccederEspacePartenaire->value) ? $this->entree('Accueil', 'bi-house', 'partenaire.tableau-de-bord', ['partenaire.tableau-de-bord']) : null,
            $user->can(Permission::VoirHistoriqueTransactions->value) ? $this->entree('Historique', 'bi-clock-history', 'partenaire.historique.index', ['partenaire.historique.*']) : null,
            $user->can(Permission::EffectuerTransaction->value) ? $this->entree('Transaction', 'bi-upc-scan', 'partenaire.transaction.verifier', ['partenaire.transaction.*'], principal: true) : null,
            $this->entree('Profil', 'bi-person-circle', 'profil', ['profil']),
        ]));
    }

    /**
     * @param  list<string>  $actives
     * @return array{libelle: string, icone: string, url: string, actif: bool, principal: bool, menu: bool}
     */
    private function entree(string $libelle, string $icone, string $route, array $actives, bool $principal = false): array
    {
        return [
            'libelle' => $libelle,
            'icone' => $icone,
            'url' => route($route),
            'actif' => request()->routeIs(...$actives),
            'principal' => $principal,
            'menu' => false,
        ];
    }
}
