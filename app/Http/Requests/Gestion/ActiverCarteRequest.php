<?php

namespace App\Http\Requests\Gestion;

use App\Models\Carte;
use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActiverCarteRequest extends FormRequest
{
    /**
     * Lettres (accents compris), espaces, apostrophes et tirets.
     */
    public const REGEX_NOM = "/^[\pL][\pL\s'’\-]*$/u";

    public function authorize(): bool
    {
        return $this->user()->can('create', Carte::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_carte' => preg_replace('/\s+/', '', (string) $this->input('numero_carte')),
            'numero_carte_confirmation' => preg_replace('/\s+/', '', (string) $this->input('numero_carte_confirmation')),
            'nom' => mb_strtoupper($this->nettoyer('nom')),
            'prenom' => mb_convert_case($this->nettoyer('prenom'), MB_CASE_TITLE),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero_carte' => ['required', 'string', 'regex:/^\d{7}$/', 'confirmed'],
            'numero_carte_confirmation' => ['required', 'string'],
            'nom' => ['required', 'string', 'min:2', 'max:100', 'regex:'.self::REGEX_NOM],
            'prenom' => ['required', 'string', 'min:2', 'max:150', 'regex:'.self::REGEX_NOM],
            'pays_telephone' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'telephone' => ['required', 'string', 'max:25', new TelephoneValide($this->input('pays_telephone'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_carte.regex' => 'Le numéro de carte doit comporter exactement 7 chiffres.',
            'numero_carte.confirmed' => 'Les deux saisies du numéro de carte ne correspondent pas.',
            'nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'prenom.regex' => 'Les prénoms ne peuvent contenir que des lettres, espaces, apostrophes et tirets.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['prenom' => 'prénoms', 'numero_carte_confirmation' => 'confirmation du numéro de carte', 'pays_telephone' => 'pays du téléphone'];
    }

    /**
     * Données validées, téléphone normalisé au format E.164 (pays choisi, Côte d'Ivoire par défaut).
     *
     * @return array{numero_carte: string, nom: string, prenom: string, telephone: string}
     */
    public function donnees(): array
    {
        $donnees = $this->safe()->only(['numero_carte', 'nom', 'prenom', 'telephone']);
        $donnees['telephone'] = Telephone::normaliser($donnees['telephone'], $this->input('pays_telephone'));

        return $donnees;
    }

    private function nettoyer(string $champ): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $this->input($champ)));
    }
}
