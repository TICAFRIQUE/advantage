<?php

namespace App\Http\Requests\Partenaire;

use App\Enums\StatutPartenaire;
use App\Services\PartenaireCourant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin / superadmin : choix du partenaire pour le compte duquel ils agissent.
 */
class ChoisirPartenaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PartenaireCourant::peutChoisir($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'partenaire_id' => [
                'required', 'integer',
                Rule::exists('partenaires', 'id')->where('statut', StatutPartenaire::Actif->value)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['partenaire_id.exists' => 'Ce partenaire n\'existe pas ou n\'est pas actif.'];
    }
}
