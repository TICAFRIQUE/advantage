<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StatutUtilisateur;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['nom', 'nom_utilisateur', 'email', 'telephone', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'statut' => StatutUtilisateur::class,
            'tentatives_echouees' => 'integer',
            'verrouille_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
        ];
    }

    /**
     * Partenaire auquel l'opérateur est rattaché (rôle partenaire uniquement).
     *
     * @return BelongsTo<Partenaire, $this>
     */
    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class);
    }

    /**
     * Auteur de la création du compte (null : console ou seeder).
     *
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id')->withTrashed();
    }

    /**
     * Auteur de la dernière modification de la fiche du compte.
     *
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id')->withTrashed();
    }

    /**
     * Auteur de la suppression (archivage), null si actif ou restauré.
     *
     * @return BelongsTo<User, $this>
     */
    public function supprimePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supprime_par_id')->withTrashed();
    }

    /**
     * Cartes activées par cet agent.
     *
     * @return HasMany<Carte, $this>
     */
    public function cartesActivees(): HasMany
    {
        return $this->hasMany(Carte::class, 'active_par_id');
    }

    /**
     * Transactions validées par ce compte (opérateur ou back-office).
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactionsValidees(): HasMany
    {
        return $this->hasMany(Transaction::class, 'valide_par_id');
    }

    /**
     * Nom d'utilisateur toujours stocké en minuscules (connexion insensible à la casse).
     *
     * @return Attribute<string, string>
     */
    protected function nomUtilisateur(): Attribute
    {
        return Attribute::make(set: fn (string $valeur) => mb_strtolower(trim($valeur)));
    }

    /**
     * Rôle déterminant l'espace d'accueil et le libellé affiché : le rôle
     * système le plus élevé, sinon le premier rôle personnalisé.
     */
    public function rolePrincipal(): ?RoleUtilisateur
    {
        /** @var ?RoleUtilisateur */
        return $this->roles->sortBy(fn (RoleUtilisateur $role) => [$role->rang(), $role->id])->first();
    }

    /**
     * Compte du back-office : au moins un rôle de l'espace « gestion »
     * (rôles système ou personnalisés).
     */
    public function estDuBackOffice(): bool
    {
        return $this->roles->contains(fn (RoleUtilisateur $role) => $role->espace() === 'gestion');
    }

    /**
     * Libellé d'auteur affiché sur chaque action : « Nom · Rôle ».
     */
    public function libelleActeur(): string
    {
        $role = $this->rolePrincipal()?->libelle();

        return $role ? "{$this->nom} · {$role}" : $this->nom;
    }

    /**
     * Initiales pour l'avatar (2 lettres maximum).
     */
    public function initiales(): string
    {
        $mots = preg_split('/\s+/u', trim($this->nom), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(mb_substr($mots[0], 0, 1).(count($mots) > 1 ? mb_substr(end($mots), 0, 1) : ''));
    }

    public function estVerrouille(): bool
    {
        return $this->verrouille_le !== null;
    }

    public function estActif(): bool
    {
        return $this->statut === StatutUtilisateur::Actif && ! $this->estVerrouille();
    }
}
