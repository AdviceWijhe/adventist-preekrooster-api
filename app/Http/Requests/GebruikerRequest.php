<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GebruikerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('gebruiker')?->id;

        return [
            'voornaam' => ['required', 'string', 'max:50'],
            'tussenvoegsel' => ['nullable', 'string', 'max:20'],
            'achternaam' => ['required', 'string', 'max:50'],
            'initialen' => ['nullable', 'string', 'max:15'],
            'geslacht' => ['required', 'in:m,v,o'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', Password::defaults()],
            'taal' => ['required', 'in:nl,en'],
            'gemeente_id' => ['nullable', 'exists:gemeentes,id'],
            'gemeente_ids' => ['sometimes', 'array'],
            'gemeente_ids.*' => ['integer', 'exists:gemeentes,id'],
            'functie' => ['nullable', 'string', 'max:50'],
            'functies' => ['sometimes', 'array'],
            'functies.*' => ['string', 'exists:functies,slug'],
            'spreekniveau' => ['nullable', Rule::in(array_keys(config('identiteit.spreekniveaus', [])))],
            'telefoonnummer' => ['nullable', 'string', 'max:30'],
            'mobiel' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'exists:roles,slug'],
            'active' => ['sometimes', 'boolean'],
            'statistieken_toegang' => ['sometimes', 'boolean'],
            'landelijk_actief' => ['sometimes', 'boolean'],
        ];
    }
}
