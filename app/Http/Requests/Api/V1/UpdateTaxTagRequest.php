<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\TaxTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxTagRequest extends FormRequest
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
        /** @var TaxTag|int|string|null $tag */
        $tag = $this->route('tax_tag');
        $ignoreId = $tag instanceof TaxTag ? $tag->id : $tag;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('tax_tags', 'code')->ignore($ignoreId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'applicability' => ['sometimes', 'required', 'string', Rule::in(TaxTag::APPLICABILITIES)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode tag pajak wajib diisi.',
            'code.unique' => 'Kode tag pajak sudah digunakan.',
            'name.required' => 'Nama tag pajak wajib diisi.',
            'applicability.in' => 'Klasifikasi tag pajak tidak valid.',
        ];
    }
}
