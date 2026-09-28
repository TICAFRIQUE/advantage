<?php

namespace App\Models;

use App\Enums\StatutDemandeOtp;
use Database\Factories\DemandeOtpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('demandes_otp')]
#[Fillable(['carte_id', 'partenaire_id', 'demandee_par_id', 'code_hash', 'demandee_le', 'expire_le', 'statut'])]
#[Hidden(['code_hash'])]
class DemandeOtp extends Model
{
    /** @use HasFactory<DemandeOtpFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'demandee_le' => 'datetime',
            'expire_le' => 'datetime',
            'utilisee_le' => 'datetime',
            'tentatives' => 'integer',
            'statut' => StatutDemandeOtp::class,
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
        return $this->belongsTo(Partenaire::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function demandeePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandee_par_id')->withTrashed();
    }

    /**
     * @return HasOne<Transaction, $this>
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function estExpiree(): bool
    {
        return $this->expire_le->isPast();
    }
}
