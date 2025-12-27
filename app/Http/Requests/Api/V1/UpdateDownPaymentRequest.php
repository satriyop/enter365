<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\DownPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDownPaymentRequest extends FormRequest
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
            'dp_date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'payment_method' => ['sometimes', Rule::in(DownPayment::PAYMENT_METHODS)],
            'cash_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
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
            'amount.min' => 'Amount must be at least 1.',
            'cash_account_id.exists' => 'Selected account does not exist.',
        ];
    }
}
