<?php

namespace App\Services\Listes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Définition unique d'une liste du back-office : filtres, recherche,
 * colonnes. Utilisée à la fois par l'écran (Yajra ou pagination) et par
 * l'export, qui contient donc exactement ce que l'utilisateur voit.
 *
 * @template TModel of Model
 */
abstract class Liste
{
    /**
     * @param  array<string, mixed>  $filtres  filtres validés par la Form Request de la liste
     * @param  ?string  $recherche  texte de la zone « Rechercher » du tableau
     */
    public function __construct(
        protected array $filtres,
        protected User $utilisateur,
        protected ?string $recherche = null,
    ) {}

    /**
     * Identifiant court (nom du fichier, journal).
     */
    abstract public function cle(): string;

    abstract public function titre(): string;

    /**
     * Requête filtrée, sans tri (Yajra applique le tri demandé par l'écran).
     *
     * @return Builder<TModel>
     */
    abstract public function requete(): Builder;

    /**
     * Recherche libre (zone « Rechercher » du tableau).
     *
     * @param  Builder<TModel>  $query
     */
    public function rechercher(Builder $query, string $recherche): void {}

    /**
     * Tri de l'export (celui affiché par défaut à l'écran).
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    abstract protected function trier(Builder $query): Builder;

    /**
     * @return list<string>
     */
    abstract public function colonnes(): array;

    /**
     * @param  TModel  $modele
     * @return list<string|int|float|null>
     */
    abstract public function ligne(Model $modele): array;

    /**
     * Filtres appliqués, en clair (en-tête du PDF, journal d'audit).
     *
     * @return array<string, string>
     */
    public function filtresLisibles(): array
    {
        return [];
    }

    /**
     * @return Builder<TModel>
     */
    public function requeteExport(): Builder
    {
        $query = $this->requete();

        if (filled($this->recherche)) {
            $this->rechercher($query, trim((string) $this->recherche));
        }

        return $this->trier($query);
    }

    /**
     * Filtres lisibles, recherche comprise.
     *
     * @return array<string, string>
     */
    public function descriptionFiltres(): array
    {
        return $this->filtresLisibles() + (filled($this->recherche) ? ['Recherche' => trim((string) $this->recherche)] : []);
    }
}
