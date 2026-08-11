<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Manufacturing\BomTemplate;
use App\Support\Features;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomTemplateRequest extends FormRequest
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
        $rules = [
            'code' => ['required', 'string', 'max:50', 'unique:bom_templates,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', Rule::in(array_keys(BomTemplate::getCategories()))],
            'thumbnail' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $rules['default_rule_set_id'] = Features::enabled('electrical_panel')
            ? ['nullable', 'integer', 'exists:spec_validation_rule_sets,id']
            : ['prohibited'];

        return $rules;
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => auth()->id(),
        ]);
    }
}
