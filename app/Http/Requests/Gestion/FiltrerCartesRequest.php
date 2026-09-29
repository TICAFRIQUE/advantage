<?php

namespace App\Http\Requests\Gestion;

use App\Enums\StatutCarte;
use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\Concerns\RechercheTableau;
use App\Models\Carte;
use App\Services\Listes\ListeCartes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerCartesRequest extends FormRequest implements DefinitListe
{
    use RechercheTableau;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Carte::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recherche' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::enum(StatutCarte::class)],
            'mes_activations' => ['nullable', 'boolean'],
            'expire_dans' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }

    public function liste(): ListeCartes
    {
        return new ListeCartes($this->validated(), $this->user(), $this->rechercheTableau());
    }
}
