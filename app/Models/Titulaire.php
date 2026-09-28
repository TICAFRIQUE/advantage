<?php

namespace App\Models;

use App\Enums\StatutTitulaire;
use App\Models\Concerns\TraceAuteurs;
use App\Observers\TitulaireObserver;
use App\Services\IndexAveugle;
use App\Services\Telephone;
use Database\Factories\TitulaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('titulaires')]
#[Fillable(['nom', 'prenom', 'telephone', 'numero_piece_identite'])]
#[Hidden(['numero_piece_identite', 'numero_piece_identite_hash'])]
#[ObservedBy(TitulaireObserver::class)]
class Titulaire extends Model
{
    /** @use HasFactory<TitulaireFactory> */
    use HasFactory, SoftDeletes, TraceAuteurs;

    public const COLONNE_CREATEUR = 'cree_par_id';

    public const COLONNE_MODIFICATEUR = 'modifie_par_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_piece_identite' => 'encrypted',
            'statut' => StatutTitulaire::class,
        ];
    }

    /**
     * Maintient l'empreinte HMAC synchronisée avec le numéro de pièce chiffré
     * (pièce facultative au MVP).
     */
    protected static function booted(): void
    {
        static::saving(function (Titulaire $titulaire): void {
            if ($titulaire->isDirty('numero_piece_identite')) {
                $titulaire->numero_piece_identite_hash = filled($titulaire->numero_piece_identite)
                    ? IndexAveugle::calculer($titulaire->numero_piece_identite)
                    : null;
            }
        });
    }

    /**
     * Téléphone toujours stocké au format international E.164 (+225XXXXXXXXXX pour la Côte d'Ivoire).
     *
     * @return Attribute<string, string>
     */
    protected function telephone(): Attribute
    {
        return Attribute::make(set: fn (string $valeur) => Telephone::normaliser($valeur) ?? $valeur);
    }

    /**
     * @param  Builder<Titulaire>  $query
     */
    public function scopeParTelephone(Builder $query, string $telephone, ?string $pays = null): void
    {
        $query->where('telephone', Telephone::normaliser($telephone, $pays) ?? $telephone);
    }

    /**
     * Recherche par numéro de pièce via l'empreinte (jamais en clair).
     *
     * @param  Builder<Titulaire>  $query
     */
    public function scopeParNumeroPiece(Builder $query, string $numero): void
    {
        $query->where('numero_piece_identite_hash', IndexAveugle::calculer($numero));
    }

    /**
     * Toutes les cartes du titulaire (historique des renouvellements).
     *
     * @return HasMany<Carte, $this>
     */
    public function cartes(): HasMany
    {
        return $this->hasMany(Carte::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id');
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function telephoneFormate(): string
    {
        return Telephone::formater($this->telephone);
    }
}
