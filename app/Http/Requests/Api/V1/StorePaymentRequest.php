<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PphCategory;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Carbon\Carbon;
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
            'payment_method' => ['required', 'string', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CHECK, Payment::METHOD_GIRO])],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'bill_id' => ['nullable', 'integer', 'exists:bills,id'],
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.allocatable_type' => ['required_with:allocations', 'string', Rule::in(['invoice', 'bill'])],
            'allocations.*.allocatable_id' => ['required_with:allocations', 'integer', 'min:1'],
            'allocations.*.amount' => ['required_with:allocations', 'integer', 'min:1'],
            'pph_category' => ['nullable', 'string', Rule::in(array_column(PphCategory::cases(), 'value'))],
            'pph_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph_withhold' => ['nullable', 'boolean'],
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

            // Validate cash account is an asset-type account (cash/bank)
            $cashAccountId = $this->input('cash_account_id');
            if ($cashAccountId && ! $validator->errors()->has('cash_account_id')) {
                $cashAccount = Account::find($cashAccountId);
                if ($cashAccount && $cashAccount->type !== Account::TYPE_ASSET) {
                    $validator->errors()->add('cash_account_id', 'Akun kas/bank harus bertipe aset. Akun "'.$cashAccount->name.'" bertipe '.$cashAccount->type.'.');
                }
            }

            $paymentDate = $this->input('payment_date');
            if ($paymentDate && ! $validator->errors()->has('payment_date')) {
                try {
                    FiscalPeriod::assertOpenForPosting(Carbon::parse($paymentDate));
                } catch (BusinessRuleException $exception) {
                    $validator->errors()->add('payment_date', $exception->getMessage());
                }
            }

            // Early overpayment check for invoices
            $amount = (int) $this->input('amount', 0);
            if ($invoiceId && $amount > 0 && ! $validator->errors()->has('invoice_id')) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice && $amount > $invoice->getOutstandingAmount()) {
                    $validator->errors()->add('amount', 'Jumlah pembayaran ('.number_format($amount).') melebihi sisa tagihan ('.number_format($invoice->getOutstandingAmount()).').');
                }
            }

            // Early overpayment check for bills
            if ($billId && $amount > 0 && ! $validator->errors()->has('bill_id')) {
                $bill = Bill::find($billId);
                if ($bill && $amount > $bill->getOutstandingAmount()) {
                    $validator->errors()->add('amount', 'Jumlah pembayaran (('.number_format($amount).') melebihi sisa tagihan ('.number_format($bill->getOutstandingAmount()).').');
                }
            }
        });
    }
}
