<?php

namespace App\Http\Requests\Gestion;

use App\Models\Partenaire;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création et modification d'un partenaire (taux compris).
 */
class EnregistrerPartenaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partenaire = $this->route('partenaire');

        return $partenaire instanceof Partenaire
            ? $this->user()->can('update', $partenaire)
            : $this->user()->can('create', Partenaire::class);
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (string $champ) => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input($champ))) ?: null;

        $this->merge([
            'nom' => $nettoyer('nom'),
            'secteur' => $nettoyer('secteur'),
            'localisation' => $nettoyer('localisation'),
            'contact' => $nettoyer('contact'),
            // Virgule décimale acceptée (saisie française).
            'taux_reduction' => str_replace(',', '.', (string) $this->input('taux_reduction')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:150'],
            'secteur' => ['nullable', 'string', 'max:100'],
            'localisation' => ['nullable', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:150'],
            'taux_reduction' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'taux_reduction.gt' => 'Le taux de réduction doit être supérieur à 0 %.',
            'taux_reduction.max' => 'Le taux de réduction ne peut pas dépasser 100 %.',
        ];
    }
}
