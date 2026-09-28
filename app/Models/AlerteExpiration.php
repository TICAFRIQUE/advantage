<?php

namespace App\Models;

use App\Enums\CanalAlerte;
use App\Enums\PalierAlerte;
use App\Enums\StatutLivraison;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('alertes_expiration')]
#[Fillable(['carte_id', 'palier', 'canal', 'envoyee_le', 'statut_livraison'])]
class AlerteExpiration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'palier' => PalierAlerte::class,
            'canal' => CanalAlerte::class,
            'statut_livraison' => StatutLivraison::class,
            'envoyee_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Carte, $this>
     */
    public function carte(): BelongsTo
    {
        return $this->belongsTo(Carte::class);
    }
}
