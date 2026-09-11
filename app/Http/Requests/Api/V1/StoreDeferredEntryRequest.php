<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeferredEntryRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:deferred_entries,code'],
            'name' => ['required', 'string', 'max:200'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'start_date' => ['required', 'date'],
            'deferred_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'recognition_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'counterpart_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode entri tangguhan wajib diisi.',
            'code.unique' => 'Kode entri tangguhan sudah digunakan.',
            'name.required' => 'Nama entri tangguhan wajib diisi.',
            'amount.required' => 'Jumlah tangguhan wajib diisi.',
            'duration_months.required' => 'Tenor tangguhan wajib diisi.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'deferred_account_id.required' => 'Akun tangguhan wajib dipilih.',
            'recognition_account_id.required' => 'Akun pengakuan wajib dipilih.',
            'counterpart_account_id.required' => 'Akun kas/bank wajib dipilih.',
        ];
    }
}
