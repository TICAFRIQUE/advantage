<?php

namespace App\Http\Requests\Partenaire;

use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\Concerns\RechercheTableau;
use App\Models\Transaction;
use App\Services\Listes\ListeHistoriquePartenaire;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de l'historique du partenaire (écran et export).
 */
class FiltrerHistoriqueRequest extends FormRequest implements DefinitListe
{
    use RechercheTableau;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Transaction::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ];
    }

    public function liste(): ListeHistoriquePartenaire
    {
        return new ListeHistoriquePartenaire($this->validated(), $this->user(), $this->rechercheTableau());
    }
}
