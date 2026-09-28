<?php

namespace App\Models;

use App\Enums\TypeOperationCarte;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Historique permanent des opérations sur une carte (ajout seul). Alimenté par
 * CarteObserver (activation, statuts) et ModifierTitulaireAction.
 */
#[Table('operations_cartes')]
#[Fillable(['carte_id', 'type', 'effectuee_par_id', 'motif', 'effectuee_le'])]
class OperationCarte extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeOperationCarte::class,
            'effectuee_le' => 'datetime',
        ];
    }

    /**
     * Enregistre une opération, attribuée à l'utilisateur connecté sauf
     * auteur explicite (null = système).
     */
    public static function enregistrer(
        Carte $carte,
        TypeOperationCarte $type,
        ?string $motif = null,
        ?User $auteur = null,
        bool $systeme = false,
    ): self {
        return self::create([
            'carte_id' => $carte->id,
            'type' => $type,
            'effectuee_par_id' => $systeme ? null : ($auteur ?? Auth::user())?->getKey(),
            'motif' => $motif,
            'effectuee_le' => now(),
        ]);
    }

    /**
     * @return BelongsTo<Carte, $this>
     */
    public function carte(): BelongsTo
    {
        return $this->belongsTo(Carte::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function effectueePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectuee_par_id')->withTrashed();
    }

    public function libelleAuteur(): string
    {
        return $this->effectueePar?->libelleActeur() ?? 'Système';
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('L\'historique des opérations est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('L\'historique des opérations est en ajout seul.'));
    }
}
