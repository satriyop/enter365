<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\DownPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDownPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([DownPayment::TYPE_RECEIVABLE, DownPayment::TYPE_PAYABLE])],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'dp_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(DownPayment::PAYMENT_METHODS)],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Down payment type is required.',
            'type.in' => 'Down payment type must be either receivable or payable.',
            'contact_id.required' => 'Contact is required.',
            'contact_id.exists' => 'Selected contact does not exist.',
            'dp_date.required' => 'Down payment date is required.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 1.',
            'payment_method.required' => 'Payment method is required.',
            'cash_account_id.required' => 'Cash/bank account is required.',
            'cash_account_id.exists' => 'Selected account does not exist.',
        ];
    }
}
