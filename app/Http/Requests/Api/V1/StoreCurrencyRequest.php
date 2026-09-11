<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:200'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_base_currency' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode mata uang wajib diisi.',
            'code.size' => 'Kode mata uang harus 3 huruf (ISO 4217).',
            'code.unique' => 'Kode mata uang sudah digunakan.',
            'name.required' => 'Nama mata uang wajib diisi.',
            'symbol.required' => 'Simbol mata uang wajib diisi.',
        ];
    }
}
