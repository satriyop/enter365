<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * Validate parent account type compatibility.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parentId = $this->input('parent_id');
            if (! $parentId) {
                return;
            }

            $parent = Account::find($parentId);
            if (! $parent) {
                return;
            }

            if ($parent->type !== $this->input('type')) {
                $validator->errors()->add(
                    'parent_id',
                    'Tipe akun induk harus sama dengan tipe akun yang dibuat.'
                );
            }
        });
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
