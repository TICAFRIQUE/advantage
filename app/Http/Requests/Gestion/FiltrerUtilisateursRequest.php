<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\Concerns\RechercheTableau;
use App\Services\Listes\ListeUtilisateurs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerUtilisateursRequest extends FormRequest implements DefinitListe
{
    use RechercheTableau;

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
            'role' => ['nullable', Rule::exists('roles', 'name')->where('espace', 'gestion')],
            'etat' => ['nullable', Rule::in(array_keys(self::ETATS))],
        ];
    }

    public function liste(): ListeUtilisateurs
    {
        return new ListeUtilisateurs($this->validated(), $this->user(), $this->rechercheTableau());
    }
}
