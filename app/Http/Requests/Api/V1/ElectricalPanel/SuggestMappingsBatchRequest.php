<?php

namespace App\Http\Requests\Api\V1\ElectricalPanel;

use Illuminate\Foundation\Http\FormRequest;

class SuggestMappingsBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
