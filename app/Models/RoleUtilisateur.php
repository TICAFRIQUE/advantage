<?php

namespace App\Models;

use App\Enums\Role;
use App\Observers\RoleUtilisateurObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as RoleSpatie;

/**
 * Rôle (table Spatie « roles ») : les 4 rôles système décrits dans
 * config/permissions.php (métadonnées recopiées par permissions:synchroniser)
 * et les rôles personnalisés créés dans Paramètres › Rôles et permissions.
 *
 * Les méthodes libelle() et espace() portent le nom des colonnes : lire les
 * colonnes via $attributes, jamais $this->libelle (pris pour une relation
 * quand l'attribut n'est pas chargé).
 *
 * @property string $name
 * @property ?string $libelle
 * @property string $espace
 * @property bool $systeme
 */
#[ObservedBy(RoleUtilisateurObserver::class)]
class RoleUtilisateur extends RoleSpatie
{
    protected function casts(): array
    {
        return ['systeme' => 'boolean'];
    }

    /**
     * Rôle système correspondant, null pour un rôle personnalisé.
     */
    public function systeme(): ?Role
    {
        return Role::tryFrom($this->name);
    }

    public function estSysteme(): bool
    {
        return $this->systeme() !== null;
    }

    /**
     * Rôle dont les permissions ne sont jamais modifiables (superadmin).
     */
    public function estVerrouille(): bool
    {
        return $this->systeme()?->estVerrouille() ?? false;
    }

    public function libelle(): string
    {
        return $this->systeme()?->libelle() ?? (($this->attributes['libelle'] ?? null) ?: $this->name);
    }

    /**
     * gestion (back-office) ou partenaire : la configuration fait foi pour
     * un rôle système.
     */
    public function espace(): string
    {
        return $this->systeme()?->espace() ?? ($this->attributes['espace'] ?? 'gestion');
    }

    public function routeAccueil(): string
    {
        return $this->espace() === 'partenaire' ? 'partenaire.tableau-de-bord' : 'gestion.tableau-de-bord';
    }

    /**
     * Rang d'affichage : rôles système dans l'ordre de l'enum, puis personnalisés.
     */
    public function rang(): int
    {
        $position = array_search($this->name, Role::valeurs(), true);

        return $position === false ? 100 : $position;
    }

    /**
     * @param  Builder<RoleUtilisateur>  $query
     */
    public function scopeDeLEspace(Builder $query, string $espace): void
    {
        $query->where('espace', $espace);
    }

    /**
     * Normalise un rôle désigné par l'enum, son nom ou le modèle.
     */
    public static function depuis(Role|string|self $role): self
    {
        return $role instanceof self ? $role : static::findByName($role instanceof Role ? $role->value : $role, 'web');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id')->withTrashed();
    }
}
