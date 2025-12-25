<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'integer', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'integer', 'min:0'],
            'auto_post' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_date.required' => 'Tanggal jurnal wajib diisi.',
            'description.required' => 'Deskripsi jurnal wajib diisi.',
            'lines.required' => 'Baris jurnal wajib diisi.',
            'lines.min' => 'Jurnal harus memiliki minimal 2 baris.',
            'lines.*.account_id.required' => 'Akun wajib diisi untuk setiap baris.',
            'lines.*.account_id.exists' => 'Akun tidak ditemukan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as $line) {
                $totalDebit += $line['debit'] ?? 0;
                $totalCredit += $line['credit'] ?? 0;
            }

            if ($totalDebit !== $totalCredit) {
                $validator->errors()->add('lines', 'Total debit harus sama dengan total kredit. Debit: ' . $totalDebit . ', Kredit: ' . $totalCredit);
            }
        });
    }
}
