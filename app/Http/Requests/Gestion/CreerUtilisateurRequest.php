<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Http\Requests\Gestion\Concerns\ValideCompte;
use App\Models\RoleUtilisateur;
use App\Services\Droits\GardeDroits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un compte du back-office : seuls les rôles que l'auteur peut
 * attribuer sont acceptés (un admin ne crée que des agents).
 */
class CreerUtilisateurRequest extends FormRequest
{
    use ValideCompte;

    public function authorize(): bool
    {
        return $this->user()->can(Permission::GererUtilisateurs->value);
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
        $roles = array_map(fn (RoleUtilisateur $role) => $role->name, GardeDroits::rolesGestionAttribuables($this->user()));

        return $this->reglesCompte() + ['role' => ['required', 'string', Rule::in($roles)]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messagesCompte() + ['role.in' => 'Vous ne pouvez pas attribuer ce rôle.'];
    }

    public function role(): RoleUtilisateur
    {
        return RoleUtilisateur::depuis($this->validated('role'));
    }
}
