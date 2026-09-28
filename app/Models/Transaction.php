<?php

namespace App\Models;

use App\Enums\StatutTransaction;
use App\Observers\TransactionObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passage validé d'un titulaire chez un partenaire, au taux appliqué.
 * Aucun montant n'est enregistré.
 */
#[Table('transactions')]
#[ObservedBy(TransactionObserver::class)]
#[Fillable(['carte_id', 'partenaire_id', 'demande_otp_id', 'valide_par_id', 'taux_applique', 'validee_le', 'statut'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taux_applique' => 'decimal:2',
            'validee_le' => 'datetime',
            'statut' => StatutTransaction::class,
        ];
    }

    /**
     * @return BelongsTo<Carte, $this>
     */
    public function carte(): BelongsTo
    {
        return $this->belongsTo(Carte::class);
    }

    /**
     * @return BelongsTo<Partenaire, $this>
     */
    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class);
    }

    /**
     * @return BelongsTo<DemandeOtp, $this>
     */
    public function demandeOtp(): BelongsTo
    {
        return $this->belongsTo(DemandeOtp::class);
    }

    /**
     * Opérateur partenaire ayant validé le passage.
     *
     * @return BelongsTo<User, $this>
     */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }
}
