<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:contacts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([Contact::TYPE_CUSTOMER, Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['integer', 'min:0'],
            'payment_term_days' => ['integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kontak wajib diisi.',
            'code.unique' => 'Kode kontak sudah digunakan.',
            'name.required' => 'Nama kontak wajib diisi.',
            'type.required' => 'Tipe kontak wajib diisi.',
            'type.in' => 'Tipe kontak tidak valid. Pilih: customer, supplier, atau both.',
        ];
    }
}
