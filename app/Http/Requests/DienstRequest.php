<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DienstRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== 'bijzonder') {
            $this->merge(['bijzonderheid_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'datum' => ['required', 'date'],
            'gemeente_id' => ['required', 'exists:gemeentes,id'],
            'type' => ['required', 'in:dienst,reguliere_dienst,bijzonder'],
            'eigeninvulling' => ['nullable', 'string', 'max:500'],
            'bijzonderheid_id' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === 'bijzonder'),
                'nullable',
                'exists:bijzonderheden,id',
            ],
            'taal' => ['nullable', 'in:nl,en'],
            'dienstwijze' => ['nullable', 'in:fysiek,digitaal,fysiek_digitaal'],
        ];
    }
}
