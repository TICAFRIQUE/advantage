<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Models\RoleUtilisateur;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création ou modification d'un rôle : libellé (rôles personnalisés) et
 * permissions cochées. Les droits fins sont revérifiés par l'action.
 */
class EnregistrerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GererRoles->value);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('libelle')) {
            $this->merge(['libelle' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('libelle'))) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        // Création : nom obligatoire ; rôle personnalisé : facultatif (champ désactivé
        // si l'on ne peut pas le renommer) ; rôle système : nom fixé par la configuration.
        $regle = match (true) {
            ! $role instanceof RoleUtilisateur => 'required',
            $role->estSysteme() => 'prohibited',
            default => 'nullable',
        };

        return [
            'libelle' => [$regle, 'string', 'min:2', 'max:60',
                Rule::unique('roles', 'libelle')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::valeurs())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.unique' => 'Un rôle porte déjà ce nom.',
            'libelle.prohibited' => 'Le nom d\'un rôle système ne se modifie pas.',
        ];
    }

    public function libelle(): ?string
    {
        return $this->validated('libelle');
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_values($this->validated('permissions') ?? []);
    }
}
