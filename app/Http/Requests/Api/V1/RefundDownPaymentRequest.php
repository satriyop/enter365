<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\DownPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundDownPaymentRequest extends FormRequest
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
            'amount' => ['sometimes', 'integer', 'min:1'],
            'refund_date' => ['sometimes', 'date'],
            'payment_method' => ['sometimes', Rule::in(DownPayment::PAYMENT_METHODS)],
            'cash_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'amount.min' => 'Refund amount must be at least 1.',
            'cash_account_id.exists' => 'Selected account does not exist.',
        ];
    }
}
