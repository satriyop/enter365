<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoodsReceiptNoteRequest extends FormRequest
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
            'receipt_date' => ['sometimes', 'date'],
            'supplier_do_number' => ['sometimes', 'string', 'max:100'],
            'supplier_invoice_number' => ['sometimes', 'string', 'max:100'],
            'vehicle_number' => ['sometimes', 'string', 'max:50'],
            'driver_name' => ['sometimes', 'string', 'max:100'],
            'notes' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
