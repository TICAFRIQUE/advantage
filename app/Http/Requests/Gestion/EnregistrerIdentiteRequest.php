<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Identité de l'application : noms et logo (PNG, JPG ou WebP ; jamais SVG,
 * qui peut contenir du script).
 */
class EnregistrerIdentiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GererParametres->value);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom_application' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('nom_application'))),
            'nom_organisation' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('nom_organisation'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom_application' => ['required', 'string', 'min:2', 'max:40'],
            'nom_organisation' => ['required', 'string', 'min:2', 'max:60'],
            'logo' => ['nullable', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(1024), 'dimensions:min_width=64,min_height=64'],
            'logo_defaut' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.dimensions' => 'Le logo doit mesurer au moins 64 × 64 pixels.',
            'logo.max' => 'Le logo ne doit pas dépasser 1 Mo.',
        ];
    }
}
