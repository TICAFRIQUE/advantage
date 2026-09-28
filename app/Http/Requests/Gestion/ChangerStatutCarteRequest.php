<?php

namespace App\Http\Requests\Gestion;

use App\Enums\StatutCarte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangerStatutCarteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changerStatut', $this->route('carte'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motif' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('motif')))]);
    }

    /**
     * L'expiration n'est jamais manuelle : seuls actif, suspendu et révoqué
     * peuvent être demandés.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in([StatutCarte::Active->value, StatutCarte::Suspendue->value, StatutCarte::Revoquee->value])],
            'motif' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }

    public function statutCible(): StatutCarte
    {
        return StatutCarte::from($this->validated('statut'));
    }
}
