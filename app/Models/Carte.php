<?php

namespace App\Models;

use App\Enums\StatutCarte;
use App\Models\Concerns\TraceAuteurs;
use App\Observers\CarteObserver;
use Database\Factories\CarteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('cartes')]
#[Fillable(['numero_carte', 'titulaire_id', 'active_par_id', 'active_le', 'statut', 'motif_statut'])]
#[ObservedBy(CarteObserver::class)]
class Carte extends Model
{
    /** @use HasFactory<CarteFactory> */
    use HasFactory, SoftDeletes, TraceAuteurs;

    public const COLONNE_CREATEUR = 'active_par_id';

    public const COLONNE_MODIFICATEUR = 'modifie_par_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_le' => 'datetime',
            'expire_le' => 'datetime',
            'statut' => StatutCarte::class,
        ];
    }

    /**
     * `expire_le` est toujours dérivé de `active_le` (+ durée de validité),
     * jamais saisissable librement.
     */
    protected static function booted(): void
    {
        static::saving(function (Carte $carte): void {
            $carte->expire_le = $carte->active_le?->copy()
                ->addMonthsNoOverflow((int) config('plateforme.carte.duree_validite_mois', 12));
        });
    }

    /**
     * @return BelongsTo<Titulaire, $this>
     */
    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(Titulaire::class);
    }

    /**
     * Agent ayant activé la carte.
     *
     * @return BelongsTo<User, $this>
     */
    public function activePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'active_par_id');
    }

    /**
     * Dernier utilisateur ayant modifié la carte.
     *
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<DemandeOtp, $this>
     */
    public function demandesOtp(): HasMany
    {
        return $this->hasMany(DemandeOtp::class);
    }

    /**
     * @return HasMany<AlerteExpiration, $this>
     */
    public function alertesExpiration(): HasMany
    {
        return $this->hasMany(AlerteExpiration::class);
    }

    /**
     * Carte utilisable chez un partenaire : statut actif et date non échue,
     * indépendamment du passage du job quotidien d'expiration.
     */
    public function estUtilisable(): bool
    {
        return $this->statut === StatutCarte::Active
            && $this->expire_le !== null
            && $this->expire_le->isFuture();
    }

    /**
     * Statut réellement applicable : une carte « active » dont la date est
     * échue est considérée expirée même avant le passage du job quotidien.
     */
    public function statutEffectif(): StatutCarte
    {
        if ($this->statut === StatutCarte::Active && $this->expire_le?->isPast()) {
            return StatutCarte::Expiree;
        }

        return $this->statut;
    }

    /**
     * Perte déclarable uniquement sur une carte encore en circulation.
     */
    public function peutEtreDeclareePerdue(): bool
    {
        return in_array($this->statutEffectif(), [StatutCarte::Active, StatutCarte::Suspendue], true);
    }

    /**
     * Mois entiers restants avant expiration (0 si expirée).
     */
    public function moisRestants(): int
    {
        if ($this->expire_le === null || $this->expire_le->isPast()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInMonths($this->expire_le)));
    }

    /**
     * Numéro formaté comme sur la carte physique : « 000 000 1 ».
     */
    public function numeroFormate(): string
    {
        return trim(chunk_split($this->numero_carte, 3, ' '));
    }
}
