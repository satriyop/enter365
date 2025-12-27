<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([Payment::TYPE_RECEIVE, Payment::TYPE_SEND])],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['string', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CHECK, Payment::METHOD_GIRO])],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'bill_id' => ['nullable', 'integer', 'exists:bills,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Tipe pembayaran wajib diisi.',
            'type.in' => 'Tipe pembayaran tidak valid. Pilih: receive atau send.',
            'contact_id.required' => 'Kontak wajib dipilih.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.min' => 'Jumlah pembayaran harus lebih dari 0.',
            'cash_account_id.required' => 'Akun kas/bank wajib dipilih.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $invoiceId = $this->input('invoice_id');
            $billId = $this->input('bill_id');

            if ($invoiceId && $billId) {
                $validator->errors()->add('invoice_id', 'Tidak bisa mengisi invoice_id dan bill_id bersamaan.');
            }

            if ($type === Payment::TYPE_RECEIVE && $billId) {
                $validator->errors()->add('bill_id', 'Penerimaan tidak bisa dialokasikan ke tagihan pembelian.');
            }

            if ($type === Payment::TYPE_SEND && $invoiceId) {
                $validator->errors()->add('invoice_id', 'Pembayaran tidak bisa dialokasikan ke faktur penjualan.');
            }
        });
    }
}
