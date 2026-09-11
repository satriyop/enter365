<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'integer', 'exists:users,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'expense_date' => ['sometimes', 'date'],
            'description' => ['sometimes', 'string', 'max:500'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'tax_amount' => ['sometimes', 'integer', 'min:0'],
            'expense_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
