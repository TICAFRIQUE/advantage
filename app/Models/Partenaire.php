<?php

namespace App\Models;

use App\Enums\StatutPartenaire;
use App\Observers\PartenaireObserver;
use Database\Factories\PartenaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('partenaires')]
#[ObservedBy(PartenaireObserver::class)]
#[Fillable(['nom', 'secteur', 'localisation', 'contact', 'taux_reduction', 'statut'])]
class Partenaire extends Model
{
    /** @use HasFactory<PartenaireFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taux_reduction' => 'decimal:2',
            'statut' => StatutPartenaire::class,
        ];
    }

    /**
     * Opérateurs (utilisateurs) rattachés à ce partenaire.
     *
     * @return HasMany<User, $this>
     */
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
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
     * @return HasMany<HistoriqueTauxPartenaire, $this>
     */
    public function historiqueTaux(): HasMany
    {
        return $this->hasMany(HistoriqueTauxPartenaire::class);
    }

    /**
     * Opérateurs du partenaire (comptes au rôle partenaire).
     *
     * @return HasMany<User, $this>
     */
    public function operateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tauxFormate(): string
    {
        return rtrim(rtrim((string) $this->taux_reduction, '0'), '.').' %';
    }

    public function estActif(): bool
    {
        return $this->statut === StatutPartenaire::Actif;
    }
}
