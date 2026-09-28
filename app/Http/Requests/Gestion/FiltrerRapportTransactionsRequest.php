<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Services\Rapports\RapportTransactions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FiltrerRapportTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VoirRapportTransactions->value);
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
            'partenaire_id' => ['nullable', 'integer', 'exists:partenaires,id'],
            'valide_par_id' => ['nullable', 'integer', 'exists:users,id'],
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

    public function rapport(): RapportTransactions
    {
        return new RapportTransactions($this->validated());
    }
}
