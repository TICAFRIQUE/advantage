<?php

namespace App\Http\Requests\Partenaire;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmerOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valider', $this->route('demande'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $longueur = (int) config('plateforme.otp.longueur', 6);

        return [
            'code' => ['required', 'string', "digits:{$longueur}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.digits' => 'Le code comporte :digits chiffres.'];
    }
}
