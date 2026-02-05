<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Contacts\Contact;
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

            // Payment terms
            'credit_limit' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'payment_term_days' => ['integer', 'min:0', 'max:365'],
            'early_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'early_discount_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            // Bank account details
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],

            // Subcontractor fields
            'is_subcontractor' => ['boolean'],
            'subcontractor_services' => ['nullable', 'array'],
            'subcontractor_services.*' => ['string', 'max:100'],
            'hourly_rate' => ['nullable', 'integer', 'min:0'],
            'daily_rate' => ['nullable', 'integer', 'min:0'],

            'notes' => ['nullable', 'string'],
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
            'currency.size' => 'Kode mata uang harus 3 karakter (contoh: IDR, USD).',
            'early_discount_percent.max' => 'Diskon pembayaran awal maksimal 100%.',
            'early_discount_days.max' => 'Hari diskon maksimal 365 hari.',
            'bank_account_number.max' => 'Nomor rekening maksimal 30 karakter.',
            'subcontractor_services.array' => 'Layanan subkontraktor harus berupa daftar.',
            'hourly_rate.min' => 'Tarif per jam tidak boleh negatif.',
            'daily_rate.min' => 'Tarif per hari tidak boleh negatif.',
        ];
    }
}
