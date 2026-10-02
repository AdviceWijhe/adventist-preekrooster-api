<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DienstRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'datum' => ['required', 'date'],
            'gemeente_id' => ['required', 'exists:gemeentes,id'],
            'type' => ['required', 'in:sabbatschool,eredienst,speciaal'],
            'eigeninvulling' => ['nullable', 'string', 'max:500'],
            'bijzonderheid_id' => ['nullable', 'exists:bijzonderheden,id'],
            'taal' => ['nullable', 'in:nl,en'],
            'dienstwijze' => ['nullable', 'in:fysiek,digitaal,fysiek_digitaal'],
        ];
    }
}
