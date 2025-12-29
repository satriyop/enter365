<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\BomTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomTemplateRequest extends FormRequest
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
        $templateId = $this->route('bom_template')?->id ?? $this->route('bomTemplate')?->id;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('bom_templates', 'code')->ignore($templateId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', Rule::in(array_keys(BomTemplate::getCategories()))],
            'thumbnail' => ['nullable', 'image', 'max:2048'],
            'default_rule_set_id' => ['nullable', 'integer', 'exists:spec_validation_rule_sets,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode template harus diisi.',
            'code.unique' => 'Kode template sudah digunakan.',
            'name.required' => 'Nama template harus diisi.',
            'category.in' => 'Kategori tidak valid.',
            'thumbnail.image' => 'Thumbnail harus berupa gambar.',
            'thumbnail.max' => 'Ukuran thumbnail maksimal 2MB.',
        ];
    }
}
