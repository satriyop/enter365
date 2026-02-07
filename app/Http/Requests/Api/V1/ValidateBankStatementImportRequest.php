<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ValidateBankStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('accounting.bank_import.max_file_size_kb', 10240);

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxSize}"],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File CSV wajib diupload.',
            'file.file' => 'File yang diupload tidak valid.',
            'file.mimes' => 'File harus berformat CSV atau TXT.',
            'file.max' => 'Ukuran file maksimal :max KB.',
            'account_id.required' => 'Akun bank wajib dipilih.',
            'account_id.exists' => 'Akun bank tidak ditemukan.',
        ];
    }
}
