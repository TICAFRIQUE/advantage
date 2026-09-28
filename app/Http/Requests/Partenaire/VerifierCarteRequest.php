<?php

namespace App\Http\Requests\Partenaire;

use App\Models\DemandeOtp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Numéro de carte saisi en caisse (vérification et demande de code).
 */
class VerifierCarteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DemandeOtp::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['numero_carte' => preg_replace('/\s+/', '', (string) $this->input('numero_carte'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero_carte' => ['required', 'string', 'regex:/^\d{7}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['numero_carte.regex' => 'Le numéro de carte comporte 7 chiffres.'];
    }
}
