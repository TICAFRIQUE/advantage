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
            'partenaire_id' => ['nullable', 'integer', 'exists:partenaires,id'],
            'valide_par_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function rapport(): RapportTransactions
    {
        return new RapportTransactions($this->validated());
    }
}
