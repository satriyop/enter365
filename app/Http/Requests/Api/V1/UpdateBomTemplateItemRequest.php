<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\BomTemplateItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomTemplateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(array_keys(BomTemplateItem::getTypes()))],
            'component_standard_id' => ['nullable', 'integer', 'exists:component_standards,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'default_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_required' => ['nullable', 'boolean'],
            'is_quantity_variable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Tipe item harus dipilih.',
            'type.in' => 'Tipe item tidak valid.',
            'description.required' => 'Deskripsi item harus diisi.',
            'default_quantity.min' => 'Jumlah tidak boleh negatif.',
        ];
    }
}
