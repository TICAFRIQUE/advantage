<?php

namespace App\Http\Requests\Gestion;

use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierTelephoneTitulaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('modifierTelephone', $this->route('carte'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pays_telephone' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'telephone' => ['required', 'string', 'max:25', new TelephoneValide($this->input('pays_telephone'))],
        ];
    }

    public function telephone(): string
    {
        return Telephone::normaliser($this->validated('telephone'), $this->validated('pays_telephone'));
    }
}
