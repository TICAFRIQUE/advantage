<?php

namespace App\Http\Requests\Gestion;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Purge manuelle du journal : date limite (entrées antérieures supprimées,
 * jamais dans le futur) et motif obligatoire, inscrits au registre.
 */
class PurgerJournalAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::PurgerJournalAudit->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avant' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'motif' => ['required', 'string', 'min:5', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avant.before_or_equal' => 'La date limite ne peut pas être dans le futur.',
            'motif.required' => 'Le motif de la purge est obligatoire.',
            'motif.min' => 'Précisez le motif (5 caractères minimum).',
        ];
    }

    public function avant(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->validated('avant'))->startOfDay();
    }
}
