<?php

namespace App\Http\Requests\Gestion;

use App\Models\Partenaire;
use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un partenaire (taux compris). Le contact est un
 * téléphone avec indicatif pays (Côte d'Ivoire par défaut), stocké en E.164.
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
            'responsable' => $nettoyer('responsable'),
            'email' => ($email = $nettoyer('email')) === null ? null : mb_strtolower($email),
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
            'pays_contact' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'contact' => ['required', 'string', 'max:25', new TelephoneValide($this->input('pays_contact'))],
            'responsable' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['contact' => 'contact', 'pays_contact' => 'pays du contact', 'email' => 'email'];
    }

    /**
     * Données validées, contact normalisé au format E.164.
     *
     * @return array{nom: string, secteur: ?string, localisation: ?string, contact: string, responsable: ?string, email: ?string, taux_reduction: string}
     */
    public function donnees(): array
    {
        $donnees = $this->safe()->except('pays_contact');
        $donnees['contact'] = Telephone::normaliser($donnees['contact'], $this->input('pays_contact'));

        return $donnees;
    }
}
