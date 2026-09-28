<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvoyerSmsTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VoirSmsSimules->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pays_telephone' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'telephone' => ['required', 'string', 'max:25', new TelephoneValide($this->input('pays_telephone'))],
            'message' => ['required', 'string', 'min:2', 'max:160'],
        ];
    }

    public function telephone(): string
    {
        return Telephone::normaliser($this->validated('telephone'), $this->validated('pays_telephone'));
    }
}
