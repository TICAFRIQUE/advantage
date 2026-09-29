<?php

namespace App\Http\Requests\Gestion;

use App\Http\Requests\Gestion\Concerns\ValideCompte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un utilisateur rattaché à un partenaire.
 */
class CreerOperateurRequest extends FormRequest
{
    use ValideCompte;

    public function authorize(): bool
    {
        return $this->user()->can('gererOperateurs', $this->route('partenaire'));
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserCompte();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglesCompte();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messagesCompte();
    }
}
