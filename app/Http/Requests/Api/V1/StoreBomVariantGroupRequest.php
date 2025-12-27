<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\BomVariantGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomVariantGroupRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'comparison_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([
                BomVariantGroup::STATUS_DRAFT,
                BomVariantGroup::STATUS_ACTIVE,
                BomVariantGroup::STATUS_ARCHIVED,
            ])],
            'bom_ids' => ['nullable', 'array'],
            'bom_ids.*' => ['integer', 'exists:boms,id'],
            'variant_names' => ['nullable', 'array'],
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
            'product_id.required' => 'Produk harus dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'name.required' => 'Nama variant group harus diisi.',
            'name.max' => 'Nama variant group maksimal 255 karakter.',
            'bom_ids.*.exists' => 'Salah satu BOM tidak ditemukan.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => auth()->id(),
        ]);
    }
}
