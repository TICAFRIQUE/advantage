<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Role;
use App\Http\Requests\Gestion\Concerns\ValideCompte;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification de la fiche d'un compte. Le rôle n'est modifiable que pour
 * un compte du back-office, parmi les rôles que l'auteur peut attribuer.
 */
class ModifierCompteRequest extends FormRequest
{
    use ValideCompte;

    public function authorize(): bool
    {
        return $this->user()->can('gerer', $this->compte());
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserCompte();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $regles = $this->reglesCompte($this->compte()->id);

        if (! $this->compte()->hasRole(Role::Partenaire)) {
            $roles = array_map(fn (Role $role) => $role->value, GardeDroits::rolesGestionAttribuables($this->user(), $this->compte()));
            $actuel = $this->compte()->rolePrincipal()?->value;
            $regles['role'] = ['required', 'string', Rule::in(array_unique(array_filter([...$roles, $actuel])))];
        }

        return $regles;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messagesCompte() + ['role.in' => 'Vous ne pouvez pas attribuer ce rôle.'];
    }

    public function role(): ?Role
    {
        return $this->has('role') && $this->validated('role') !== null ? Role::from($this->validated('role')) : null;
    }

    public function compte(): User
    {
        return $this->route('compte');
    }
}
