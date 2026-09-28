<?php

namespace App\Http\Requests\Gestion;

use App\Enums\StatutPartenaire;
use App\Models\Partenaire;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerPartenairesRequest extends FormRequest
{
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
}
