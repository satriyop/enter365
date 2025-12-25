<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('product_categories', 'code')->ignore($this->route('product_category')),
            ],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'exists:product_categories,id',
                // Prevent circular reference
                Rule::notIn([$this->route('product_category')?->id]),
            ],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode kategori sudah digunakan.',
            'parent_id.exists' => 'Kategori induk tidak ditemukan.',
            'parent_id.not_in' => 'Kategori tidak bisa menjadi induk dari dirinya sendiri.',
        ];
    }
}
