<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
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
        $currency = $this->route('currency');

        return [
            'code' => ['sometimes', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($currency)],
            'name' => ['sometimes', 'string', 'max:200'],
            'symbol' => ['sometimes', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_base_currency' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
