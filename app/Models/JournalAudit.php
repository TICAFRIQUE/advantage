<?php

namespace App\Models;

use App\Exceptions\JournalAuditImmuableException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit append-only : toute tentative de modification ou de
 * suppression via Eloquent lève une exception.
 */
#[Table('journaux_audit')]
#[Fillable(['acteur_id', 'type_acteur', 'action', 'type_entite', 'entite_id', 'donnees', 'adresse_ip'])]
class JournalAudit extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'cree_le' => 'datetime',
        ];
    }

    /**
     * Auteur de l'action (conservé même si le compte a été supprimé depuis).
     *
     * @return BelongsTo<User, $this>
     */
    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new JournalAuditImmuableException);
        static::deleting(fn () => throw new JournalAuditImmuableException);
    }
}
