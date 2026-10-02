<?php

namespace App\Http\Requests\Gestion;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Suppression définitive d'une carte : motif obligatoire et numéro de la
 * carte retapé (garde-fou contre une suppression sur la mauvaise fiche).
 */
class SupprimerCarteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('supprimerDefinitivement', $this->route('carte'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motif' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('motif'))),
            'numero_confirmation' => preg_replace('/\D/', '', (string) $this->input('numero_confirmation')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:5', 'max:200'],
            'numero_confirmation' => [
                'required',
                function (string $attribut, mixed $valeur, Closure $echec): void {
                    if (! hash_equals((string) $this->route('carte')->numero_carte, (string) $valeur)) {
                        $echec('Le numéro saisi ne correspond pas à cette carte.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motif' => 'motif', 'numero_confirmation' => 'numéro de la carte'];
    }
}
