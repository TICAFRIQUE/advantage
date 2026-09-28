<?php

namespace App\Http\Requests\Gestion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ModifierIdentiteTitulaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('modifierTitulaire', $this->route('carte'));
    }

    /**
     * Même normalisation qu'à l'activation (NOM en majuscules, Prénoms capitalisés).
     */
    protected function prepareForValidation(): void
    {
        $nettoyer = fn (string $champ) => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input($champ)));

        $this->merge([
            'nom' => mb_strtoupper($nettoyer('nom')),
            'prenom' => mb_convert_case($nettoyer('prenom'), MB_CASE_TITLE),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:100', 'regex:'.ActiverCarteRequest::REGEX_NOM],
            'prenom' => ['required', 'string', 'min:2', 'max:150', 'regex:'.ActiverCarteRequest::REGEX_NOM],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['prenom' => 'prénoms'];
    }
}
