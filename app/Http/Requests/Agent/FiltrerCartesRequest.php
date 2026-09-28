<?php

namespace App\Http\Requests\Agent;

use App\Enums\StatutCarte;
use App\Models\Carte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerCartesRequest extends FormRequest
{
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
        ];
    }
}
