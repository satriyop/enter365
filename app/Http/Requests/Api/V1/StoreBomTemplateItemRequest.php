<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Manufacturing\BomTemplateItem;
use App\Support\AddonExtensions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomTemplateItemRequest extends FormRequest
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
        return array_merge([
            'type' => ['required', Rule::in(array_keys(BomTemplateItem::getTypes()))],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['required', 'string', 'max:255'],
            'default_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_required' => ['nullable', 'boolean'],
            'is_quantity_variable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], AddonExtensions::validationRules('bom_template_item'));
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
