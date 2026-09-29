<?php

namespace App\Services\Listes;

use App\Models\JournalAudit;
use App\Support\LibellesAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres › Journal d'audit (filtres : période, action, élément, auteur).
 *
 * @extends Liste<JournalAudit>
 */
class ListeJournalAudit extends Liste
{
    public function cle(): string
    {
        return 'journal-audit';
    }

    public function titre(): string
    {
        return 'Journal d\'audit';
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        return JournalAudit::query()->select('journaux_audit.*')->with('acteur.roles')
            ->when($f['du'] ?? null, fn ($q, string $du) => $q->where('cree_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn ($q, string $au) => $q->where('cree_le', '<=', $au.' 23:59:59'))
            ->when($f['action'] ?? null, fn ($q, string $action) => $q->where('action', $action))
            ->when($f['type_entite'] ?? null, fn ($q, string $type) => $q->where('type_entite', $type))
            ->when($f['acteur'] ?? null, function ($q, string $acteur): void {
                $texte = addcslashes(mb_strtolower(trim($acteur)), '%_\\');
                $q->whereHas('acteur', fn ($a) => $a->withTrashed()->where(fn ($a) => $a
                    ->where('nom_utilisateur', 'like', $texte.'%')
                    ->orWhere('nom', 'like', '%'.$texte.'%')));
            });
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');

        $query->where(fn ($q) => $q
            ->where('action', 'like', "%{$texte}%")
            ->orWhere('adresse_ip', 'like', "{$texte}%"));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('cree_le')->latest('id');
    }

    public function colonnes(): array
    {
        return ['Date', 'Auteur', 'Action', 'Élément', 'Adresse IP', 'Détails'];
    }

    /**
     * @param  JournalAudit  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->cree_le->format('d/m/Y H:i:s'),
            self::auteur($modele),
            LibellesAudit::action($modele->action),
            self::element($modele),
            $modele->adresse_ip,
            LibellesAudit::detailsTexte($modele->donnees),
        ];
    }

    public function filtresLisibles(): array
    {
        $f = $this->filtres;

        return array_filter([
            'Du' => $f['du'] ?? null,
            'Au' => $f['au'] ?? null,
            'Action' => filled($f['action'] ?? null) ? LibellesAudit::action($f['action']) : null,
            'Élément' => LibellesAudit::entite($f['type_entite'] ?? null),
            'Auteur' => $f['acteur'] ?? null,
        ]);
    }

    public static function auteur(JournalAudit $entree): string
    {
        return $entree->acteur?->libelleActeur()
            ?? ($entree->type_acteur === 'systeme' ? 'Système (tâche planifiée)' : 'Anonyme');
    }

    public static function element(JournalAudit $entree): ?string
    {
        $libelle = LibellesAudit::entite($entree->type_entite);

        return $libelle === null ? null : "{$libelle} #{$entree->entite_id}";
    }
}
