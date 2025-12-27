<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\MrpSuggestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMrpSuggestionRequest extends FormRequest
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
            'adjusted_quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'suggested_supplier_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'suggested_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'priority' => ['nullable', 'string', Rule::in(array_keys(MrpSuggestion::getPriorities()))],
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
            'adjusted_quantity.min' => 'Kuantitas harus lebih dari 0.',
            'suggested_supplier_id.exists' => 'Supplier yang dipilih tidak ditemukan.',
            'suggested_warehouse_id.exists' => 'Gudang yang dipilih tidak ditemukan.',
        ];
    }
}
