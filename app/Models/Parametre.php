<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paramètre de l'application (clé / valeur), modifié depuis Administration ›
 * Paramètres. Lecture via App\Services\Parametres (en cache).
 */
#[Table('parametres')]
#[Fillable(['cle', 'valeur', 'modifie_par_id'])]
class Parametre extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id')->withTrashed();
    }
}
