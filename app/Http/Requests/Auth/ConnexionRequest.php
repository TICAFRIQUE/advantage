<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Remplace la LoginRequest de Fortify : bornes strictes sur les entrées
 * (aucune requête en base ni calcul bcrypt pour une saisie aberrante),
 * et filtre anti-robots avant toute vérification du compte.
 */
class ConnexionRequest extends LoginRequest
{
    /**
     * Session : heure d'affichage du formulaire (FortifyServiceProvider).
     */
    public const CLE_AFFICHAGE = 'connexion.formulaire_affiche_le';

    /**
     * Champ invisible pour un humain, rempli par les robots qui remplissent tout.
     */
    public const CHAMP_PIEGE = 'commentaire';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom_utilisateur' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string', 'max:72'],
        ];
    }

    /**
     * Robot présumé : même message qu'un PIN erroné (rien à apprendre),
     * sans consulter le compte (aucun échec compté, aucun verrouillage).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->estUnRobot()) {
                return;
            }

            Log::info('Connexion refusée : robot présumé.', ['ip' => $this->ip()]);
            $validator->errors()->add(Fortify::username(), __('auth.failed'));
        }];
    }

    /**
     * Pas d'option « se souvenir de moi » : la durée de session est bornée.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('remember');
    }

    private function estUnRobot(): bool
    {
        if (filled($this->input(self::CHAMP_PIEGE))) {
            return true;
        }

        // Formulaire jamais affiché (envoi direct) ou envoyé trop vite.
        $afficheLe = $this->session()->get(self::CLE_AFFICHAGE);

        return ! is_int($afficheLe)
            || now()->getTimestamp() - $afficheLe < (int) config('plateforme.robots.delai_minimal_connexion');
    }
}
