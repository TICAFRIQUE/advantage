<?php

namespace App\Http\Requests\Gestion\Concerns;

use App\Rules\TelephoneValide;
use App\Services\Telephone;
use Illuminate\Validation\Rule;

/**
 * Champs communs d'un compte (création d'un utilisateur du back-office ou
 * d'un partenaire, modification d'une fiche) : même normalisation, mêmes
 * règles, même format d'identifiant que la connexion (ConnexionRequest).
 */
trait ValideCompte
{
    public const REGEX_NOM_UTILISATEUR = '/^[a-z0-9._-]+$/';

    protected function normaliserCompte(): void
    {
        $this->merge([
            'nom' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('nom'))) ?: null,
            'nom_utilisateur' => mb_strtolower(trim((string) $this->input('nom_utilisateur'))),
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function reglesCompte(?int $ignorer = null): array
    {
        // Unicité sur toute la table, comptes supprimés compris : un identifiant
        // archivé reste attaché à son historique.
        return [
            'nom' => ['nullable', 'string', 'min:2', 'max:150'],
            'nom_utilisateur' => ['required', 'string', 'min:3', 'max:50', 'regex:'.self::REGEX_NOM_UTILISATEUR, Rule::unique('users', 'nom_utilisateur')->ignore($ignorer)],
            'email' => ['nullable', 'string', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($ignorer)],
            'pays_telephone' => ['nullable', 'string', Rule::in(array_keys(Telephone::tousLesPays()))],
            'telephone' => ['nullable', 'string', 'max:25', new TelephoneValide($this->input('pays_telephone'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messagesCompte(): array
    {
        return [
            'nom_utilisateur.regex' => 'Le nom d\'utilisateur ne peut contenir que des lettres minuscules, chiffres, points, tirets et soulignés.',
            'nom_utilisateur.unique' => 'Ce nom d\'utilisateur est déjà pris.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ];
    }

    /**
     * Nom facultatif : le nom d'utilisateur en tient lieu (affichage, traçabilité).
     *
     * @return array{nom: string, nom_utilisateur: string, email: ?string, telephone: ?string}
     */
    public function donnees(): array
    {
        return [
            'nom' => filled($this->validated('nom')) ? $this->validated('nom') : $this->validated('nom_utilisateur'),
            'nom_utilisateur' => $this->validated('nom_utilisateur'),
            'email' => $this->validated('email'),
            'telephone' => filled($this->validated('telephone'))
                ? Telephone::normaliser($this->validated('telephone'), $this->validated('pays_telephone'))
                : null,
        ];
    }
}
