<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Enums\StatutCarte;
use App\Services\Rapports\RapportCartes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerRapportCartesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VoirRapportCartes->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'statut' => ['nullable', Rule::enum(StatutCarte::class)],
            'mes_activations' => ['nullable', 'boolean'],
        ];
    }

    public function rapport(): RapportCartes
    {
        $filtres = $this->validated();
        $filtres['mes_activations'] = $this->boolean('mes_activations');

        return new RapportCartes($filtres, $this->user());
    }
}
