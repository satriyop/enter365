<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncQuotationVariantOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:2'],
            'options.*.bom_id' => ['required', 'exists:boms,id'],
            'options.*.display_name' => ['required', 'string', 'max:255'],
            'options.*.tagline' => ['nullable', 'string', 'max:255'],
            'options.*.is_recommended' => ['boolean'],
            'options.*.selling_price' => ['required', 'integer', 'min:0'],
            'options.*.features' => ['nullable', 'array'],
            'options.*.features.*' => ['string'],
            'options.*.specifications' => ['nullable', 'array'],
            'options.*.warranty_terms' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.required' => 'Opsi varian harus diisi.',
            'options.min' => 'Minimal 2 opsi varian diperlukan.',
            'options.*.bom_id.required' => 'BOM harus dipilih untuk setiap opsi.',
            'options.*.display_name.required' => 'Nama tampilan harus diisi.',
            'options.*.selling_price.required' => 'Harga jual harus diisi.',
        ];
    }
}
