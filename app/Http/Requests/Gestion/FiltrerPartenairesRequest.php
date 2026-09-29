<?php

namespace App\Http\Requests\Gestion;

use App\Enums\StatutPartenaire;
use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\Concerns\RechercheTableau;
use App\Models\Partenaire;
use App\Services\Listes\ListePartenaires;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerPartenairesRequest extends FormRequest implements DefinitListe
{
    use RechercheTableau;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Partenaire::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'partenaire_id' => ['nullable', 'integer', 'exists:partenaires,id'],
            'statut' => ['nullable', Rule::enum(StatutPartenaire::class)],
        ];
    }

    public function liste(): ListePartenaires
    {
        return new ListePartenaires($this->validated(), $this->user(), $this->rechercheTableau());
    }
}
