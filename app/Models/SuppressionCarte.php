<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registre des cartes supprimées définitivement : jamais modifiable ni
 * effaçable (modèle + triggers MySQL). Seule trace durable qu'une carte a
 * existé ; aucune donnée personnelle du titulaire n'y figure.
 */
#[Table('suppressions_cartes')]
#[Fillable([
    'numero_carte', 'supprime_par_id', 'motif', 'transactions_supprimees', 'codes_supprimes',
    'alertes_supprimees', 'operations_supprimees', 'sms_supprimes', 'titulaire_supprime',
])]
class SuppressionCarte extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transactions_supprimees' => 'integer',
            'codes_supprimes' => 'integer',
            'alertes_supprimees' => 'integer',
            'operations_supprimees' => 'integer',
            'sms_supprimes' => 'integer',
            'titulaire_supprime' => 'boolean',
            'cree_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supprimePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supprime_par_id')->withTrashed();
    }

    /**
     * Numéro groupé comme sur la carte : 000 000 1.
     */
    public function numeroFormate(): string
    {
        return trim(chunk_split($this->numero_carte, 3, ' '));
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le registre des cartes supprimées est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('Le registre des cartes supprimées est en ajout seul.'));
    }
}
