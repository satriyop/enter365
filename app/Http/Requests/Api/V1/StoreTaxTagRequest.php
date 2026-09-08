<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\TaxTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if (! $this->exists('applicability')) {
            $this->merge(['applicability' => TaxTag::APPLICABILITY_TAX]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:tax_tags,code'],
            'name' => ['required', 'string', 'max:200'],
            'applicability' => ['required', 'string', Rule::in(TaxTag::APPLICABILITIES)],
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
