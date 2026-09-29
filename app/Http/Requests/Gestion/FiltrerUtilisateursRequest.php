<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerUtilisateursRequest extends FormRequest
{
    public const ETATS = ['actif' => 'Actif', 'inactif' => 'Désactivé', 'verrouille' => 'Verrouillé'];

    public function authorize(): bool
    {
        return $this->user()->can(Permission::GererUtilisateurs->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', Rule::in(array_map(fn (Role $role) => $role->value, Role::roleGestion()))],
            'etat' => ['nullable', Rule::in(array_keys(self::ETATS))],
        ];
    }
}
