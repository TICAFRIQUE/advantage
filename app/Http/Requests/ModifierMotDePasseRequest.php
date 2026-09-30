<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Services\GenerateurPin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Nouveau mot de passe du superadmin : 5 chiffres, ni suite ni chiffre répété,
 * différent de l'actuel, saisi deux fois.
 */
class ModifierMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Superadmin);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mot_de_passe' => [
                'required', 'string', 'digits:'.(int) config('plateforme.connexion.longueur_pin', 5), 'confirmed',
                function (string $attribut, mixed $valeur, Closure $echec): void {
                    if (GenerateurPin::estTrivial((string) $valeur)) {
                        $echec('Ce mot de passe est trop simple (suite ou chiffre répété).');
                    } elseif (Hash::check((string) $valeur, $this->user()->password)) {
                        $echec('Choisissez un mot de passe différent de l\'actuel.');
                    }
                },
            ],
            'mot_de_passe_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mot_de_passe' => 'nouveau mot de passe',
            'mot_de_passe_confirmation' => 'confirmation',
        ];
    }
}
