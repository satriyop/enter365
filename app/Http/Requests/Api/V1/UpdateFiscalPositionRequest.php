<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiscalPositionRequest extends FormRequest
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
        $position = $this->route('fiscal_position');
        $positionId = is_object($position) ? $position->id : $position;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('fiscal_positions', 'code')->ignore($positionId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'tax_maps' => ['sometimes', 'array'],
            'tax_maps.*.source_tax_record_id' => ['required', 'integer', 'distinct', 'exists:tax_records,id'],
            'tax_maps.*.dest_tax_record_id' => ['nullable', 'integer', 'exists:tax_records,id'],
            'account_maps' => ['sometimes', 'array'],
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
            'code.unique' => 'Kode posisi fiskal sudah digunakan.',
            'tax_maps.*.source_tax_record_id.distinct' => 'Pajak sumber tidak boleh duplikat.',
            'account_maps.*.source_account_id.distinct' => 'Akun sumber tidak boleh duplikat.',
        ];
    }
}
