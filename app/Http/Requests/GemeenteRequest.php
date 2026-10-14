<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GemeenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naam' => ['required', 'string', 'max:100'],
            'naam_kort' => ['nullable', 'string', 'max:50'],
            'adres' => ['nullable', 'string', 'max:150'],
            'plaats' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'predikant_id' => ['nullable', 'exists:users,id'],
            'contactpersoon_id' => ['nullable', 'exists:users,id'],
            'begintijd_ochtend' => ['nullable', 'string', 'max:5'],
            'begintijd_avond' => ['nullable', 'string', 'max:5'],
            'kerk' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url'],
            'livestream_url' => ['nullable', 'url'],
            'taal' => ['required', 'string', 'in:nl,en'],
            'active' => ['sometimes', 'boolean'],
            'church_plant' => ['sometimes', 'boolean'],
            'volgorde' => ['sometimes', 'integer'],
        ];
    }
}
