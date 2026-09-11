<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountReconcileRequest extends FormRequest
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
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'partner_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.journal_entry_line_id' => ['required', 'integer', 'exists:journal_entry_lines,id'],
            'items.*.amount' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Akun rekonsiliasi wajib dipilih.',
            'items.required' => 'Pilih baris jurnal yang akan direkonsiliasi.',
            'items.min' => 'Pilih minimal dua baris jurnal.',
            'items.*.journal_entry_line_id.required' => 'Baris jurnal wajib dipilih.',
        ];
    }
}
