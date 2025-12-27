<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMrpRunRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'planning_horizon_start' => ['required', 'date'],
            'planning_horizon_end' => ['required', 'date', 'after:planning_horizon_start'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'parameters' => ['nullable', 'array'],
            'parameters.include_safety_stock' => ['nullable', 'boolean'],
            'parameters.respect_moq' => ['nullable', 'boolean'],
            'parameters.respect_order_multiple' => ['nullable', 'boolean'],
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
            'planning_horizon_start.required' => 'Tanggal awal horizon perencanaan harus diisi.',
            'planning_horizon_end.required' => 'Tanggal akhir horizon perencanaan harus diisi.',
            'planning_horizon_end.after' => 'Tanggal akhir harus setelah tanggal awal.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak ditemukan.',
        ];
    }
}
