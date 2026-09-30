<?php

namespace App\Models;

use App\Enums\StatutTitulaire;
use App\Models\Concerns\TraceAuteurs;
use App\Observers\TitulaireObserver;
use App\Services\Telephone;
use Database\Factories\TitulaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
#[Fillable(['nom', 'prenom', 'telephone'])]
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
            'statut' => StatutTitulaire::class,
        ];
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
        return $this->belongsTo(User::class, 'cree_par_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id')->withTrashed();
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
