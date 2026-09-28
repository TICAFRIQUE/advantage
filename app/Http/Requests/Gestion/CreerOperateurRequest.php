<?php

namespace App\Http\Requests\Gestion;

use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un compte (opérateur partenaire, puis agent / admin à l'étape E).
 */
class CreerOperateurRequest extends FormRequest
{
    /**
     * Même format que celui accepté à la connexion (ConnexionRequest).
     */
    public const REGEX_NOM_UTILISATEUR = '/^[a-z0-9._-]+$/';

    public function authorize(): bool
    {
        return $this->user()->can('gererOperateurs', $this->route('partenaire'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('nom'))) ?: null,
            'nom_utilisateur' => mb_strtolower(trim((string) $this->input('nom_utilisateur'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['nullable', 'string', 'min:2', 'max:150'],
            'nom_utilisateur' => ['required', 'string', 'min:3', 'max:50', 'regex:'.self::REGEX_NOM_UTILISATEUR, Rule::unique('users', 'nom_utilisateur')],
            'pays_telephone' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'telephone' => ['nullable', 'string', 'max:25', new TelephoneValide($this->input('pays_telephone'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom_utilisateur.regex' => 'Le nom d\'utilisateur ne peut contenir que des lettres minuscules, chiffres, points, tirets et soulignés.',
            'nom_utilisateur.unique' => 'Ce nom d\'utilisateur est déjà pris.',
        ];
    }

    /**
     * @return array{nom: string, nom_utilisateur: string, telephone: ?string}
     */
    public function donnees(): array
    {
        return [
            // Nom facultatif : le nom d'utilisateur en tient lieu (affichage, traçabilité).
            'nom' => filled($this->validated('nom')) ? $this->validated('nom') : $this->validated('nom_utilisateur'),
            'nom_utilisateur' => $this->validated('nom_utilisateur'),
            'telephone' => filled($this->validated('telephone'))
                ? Telephone::normaliser($this->validated('telephone'), $this->validated('pays_telephone'))
                : null,
        ];
    }
}
