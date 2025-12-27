<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubcontractorInvoiceRequest extends FormRequest
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
            'gross_amount' => ['sometimes', 'integer', 'min:1'],
            'other_deductions' => ['nullable', 'integer', 'min:0'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'description' => ['nullable', 'string', 'max:2000'],
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
            'gross_amount.min' => 'Jumlah tagihan harus lebih dari 0.',
            'other_deductions.min' => 'Potongan lain tidak boleh negatif.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus setelah atau sama dengan tanggal invoice.',
        ];
    }
}
