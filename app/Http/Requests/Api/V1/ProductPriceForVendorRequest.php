<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductPriceForVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.integer' => 'Vendor tidak valid.',
            'contact_id.exists' => 'Vendor tidak ditemukan.',
            'quantity.numeric' => 'Kuantitas harus berupa angka.',
            'quantity.min' => 'Kuantitas tidak boleh negatif.',
        ];
    }
}
