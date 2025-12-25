<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tax_rate' => ['numeric', 'min:0', 'max:100'],
            'discount_amount' => ['integer', 'min:0'],
            'receivable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['string', 'max:20'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Pelanggan wajib dipilih.',
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'invoice_date.required' => 'Tanggal faktur wajib diisi.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal faktur.',
            'items.required' => 'Item faktur wajib diisi.',
            'items.min' => 'Faktur harus memiliki minimal 1 item.',
            'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ];
    }
}
