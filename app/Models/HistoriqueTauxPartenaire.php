<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('historique_taux_partenaires', timestamps: false)]
#[Fillable(['partenaire_id', 'ancien_taux', 'nouveau_taux', 'modifie_par_id', 'modifie_le'])]
class HistoriqueTauxPartenaire extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ancien_taux' => 'decimal:2',
            'nouveau_taux' => 'decimal:2',
            'modifie_le' => 'datetime',
        ];
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
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id')->withTrashed();
    }
}
