<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Enums\TypeOperationCarte;
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
            'carte' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'type' => ['nullable', Rule::enum(TypeOperationCarte::class)],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'mes_operations' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('carte')) {
            $this->merge(['carte' => preg_replace('/\s+/', '', (string) $this->input('carte'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['carte.regex' => 'Le numéro de carte comporte 7 chiffres.'];
    }

    public function rapport(): RapportCartes
    {
        $filtres = $this->validated();
        $filtres['mes_operations'] = $this->boolean('mes_operations');

        return new RapportCartes($filtres, $this->user());
    }
}
