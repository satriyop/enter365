<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z0-9_]+$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan.',
            'name.regex' => 'Nama role hanya boleh huruf kecil, angka, dan underscore.',
            'display_name.required' => 'Nama tampilan wajib diisi.',
            'permissions.*.exists' => 'Permission tidak ditemukan.',
        ];
    }
}
