<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Account::getTypes())],
            'subtype' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
            'opening_balance' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode akun wajib diisi.',
            'code.unique' => 'Kode akun sudah digunakan.',
            'name.required' => 'Nama akun wajib diisi.',
            'type.required' => 'Tipe akun wajib diisi.',
            'type.in' => 'Tipe akun tidak valid.',
        ];
    }
}
