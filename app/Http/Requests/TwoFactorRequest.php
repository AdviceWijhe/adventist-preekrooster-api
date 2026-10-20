<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('code')) ?? '';

        if ($digits === '') {
            $this->merge(['code' => '']);

            return;
        }

        $this->merge([
            'code' => str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
