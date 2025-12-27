<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\BomItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'output_quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'output_unit' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(array_keys(BomItem::getTypes()))],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
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
            'name.required' => 'Nama BOM harus diisi.',
            'product_id.required' => 'Produk output harus dipilih.',
            'product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
            'items.required' => 'Minimal satu item BOM diperlukan.',
            'items.min' => 'Minimal satu item BOM diperlukan.',
            'items.*.type.required' => 'Tipe item harus diisi.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Kuantitas item harus diisi.',
            'items.*.unit_cost.required' => 'Biaya satuan harus diisi.',
        ];
    }
}
