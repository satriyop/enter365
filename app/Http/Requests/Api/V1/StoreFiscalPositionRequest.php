<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiscalPositionRequest extends FormRequest
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
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:fiscal_positions,code'],
            'name' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'tax_maps' => ['nullable', 'array'],
            'tax_maps.*.source_tax_record_id' => ['required', 'integer', 'distinct', 'exists:tax_records,id'],
            'tax_maps.*.dest_tax_record_id' => ['nullable', 'integer', 'exists:tax_records,id'],
            'account_maps' => ['nullable', 'array'],
            'account_maps.*.source_account_id' => ['required', 'integer', 'distinct', 'exists:accounts,id'],
            'account_maps.*.dest_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode posisi fiskal wajib diisi.',
            'code.unique' => 'Kode posisi fiskal sudah digunakan.',
            'name.required' => 'Nama posisi fiskal wajib diisi.',
            'tax_maps.*.source_tax_record_id.distinct' => 'Pajak sumber tidak boleh duplikat.',
            'tax_maps.*.source_tax_record_id.exists' => 'Pajak sumber tidak ditemukan.',
            'tax_maps.*.dest_tax_record_id.exists' => 'Pajak tujuan tidak ditemukan.',
            'account_maps.*.source_account_id.distinct' => 'Akun sumber tidak boleh duplikat.',
            'account_maps.*.source_account_id.exists' => 'Akun sumber tidak ditemukan.',
            'account_maps.*.dest_account_id.exists' => 'Akun tujuan tidak ditemukan.',
        ];
    }
}
