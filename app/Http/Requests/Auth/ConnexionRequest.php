<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Remplace la LoginRequest de Fortify : bornes strictes sur les entrées
 * (aucune requête en base ni calcul bcrypt pour une saisie aberrante).
 */
class ConnexionRequest extends LoginRequest
{
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
     * Pas d'option « se souvenir de moi » : la durée de session est bornée.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('remember');
    }
}
