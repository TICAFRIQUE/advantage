<?php

namespace App\Models;

use App\Enums\TypePurge;
use App\Exceptions\JournalAuditImmuableException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registre des purges du journal d'audit : jamais modifiable ni effaçable
 * (modèle + triggers MySQL). Garantit qu'une suppression manuelle reste
 * toujours attribuable à son auteur.
 */
#[Table('purges_journal_audit')]
#[Fillable(['type', 'purge_par_id', 'supprime_avant', 'nombre_entrees', 'motif'])]
class PurgeJournalAudit extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePurge::class,
            'supprime_avant' => 'datetime',
            'nombre_entrees' => 'integer',
            'cree_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function purgePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purge_par_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new JournalAuditImmuableException);
        static::deleting(fn () => throw new JournalAuditImmuableException);
    }
}
