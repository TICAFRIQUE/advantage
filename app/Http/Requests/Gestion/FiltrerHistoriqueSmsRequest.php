<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\Concerns\RechercheTableau;
use App\Services\Listes\ListeMessagesSms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerHistoriqueSmsRequest extends FormRequest implements DefinitListe
{
    use RechercheTableau;

    public function authorize(): bool
    {
        return $this->user()->can(Permission::VoirHistoriqueSms->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
            'type' => ['nullable', 'string', Rule::enum(TypeSms::class)],
            'statut' => ['nullable', 'string', Rule::enum(StatutLivraison::class)],
        ];
    }

    public function liste(): ListeMessagesSms
    {
        return new ListeMessagesSms($this->validated(), $this->user(), $this->rechercheTableau());
    }
}
