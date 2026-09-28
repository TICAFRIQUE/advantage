<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeclarerPerteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('declarerPerte', $this->route('carte'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motif' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('motif')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
