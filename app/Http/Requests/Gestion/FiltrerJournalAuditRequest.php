<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use App\Support\LibellesAudit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerJournalAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VoirJournalAudit->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
            'action' => ['nullable', 'string', Rule::in(LibellesAudit::codesActions())],
            'type_entite' => ['nullable', 'string', Rule::in(array_keys(LibellesAudit::ENTITES))],
            'acteur' => ['nullable', 'string', 'max:50'],
        ];
    }
}
