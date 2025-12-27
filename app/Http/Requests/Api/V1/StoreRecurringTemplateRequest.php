<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\RecurringTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in([RecurringTemplate::TYPE_INVOICE, RecurringTemplate::TYPE_BILL])],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'frequency' => ['required', Rule::in([
                RecurringTemplate::FREQUENCY_DAILY,
                RecurringTemplate::FREQUENCY_WEEKLY,
                RecurringTemplate::FREQUENCY_MONTHLY,
                RecurringTemplate::FREQUENCY_QUARTERLY,
                RecurringTemplate::FREQUENCY_YEARLY,
            ])],
            'interval' => ['integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'occurrences_limit' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tax_rate' => ['numeric', 'min:0', 'max:100'],
            'discount_amount' => ['integer', 'min:0'],
            'early_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'early_discount_days' => ['nullable', 'integer', 'min:0'],
            'payment_term_days' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['string', 'max:20'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'items.*.expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
            'auto_post' => ['boolean'],
            'auto_send' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama template wajib diisi.',
            'type.required' => 'Tipe template wajib dipilih.',
            'type.in' => 'Tipe template harus invoice atau bill.',
            'contact_id.required' => 'Kontak wajib dipilih.',
            'contact_id.exists' => 'Kontak tidak ditemukan.',
            'frequency.required' => 'Frekuensi wajib dipilih.',
            'frequency.in' => 'Frekuensi tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.after' => 'Tanggal akhir harus setelah tanggal mulai.',
            'items.required' => 'Item wajib diisi.',
            'items.min' => 'Minimal 1 item harus diisi.',
            'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ];
    }
}
