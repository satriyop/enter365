<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubcontractorWorkOrderRequest extends FormRequest
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
            'subcontractor_id' => ['sometimes', 'integer', 'exists:contacts,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scope_of_work' => ['nullable', 'string', 'max:5000'],
            'agreed_amount' => ['sometimes', 'integer', 'min:0'],
            'actual_amount' => ['nullable', 'integer', 'min:0'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scheduled_start_date' => ['nullable', 'date'],
            'scheduled_end_date' => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:1000'],
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
            'subcontractor_id.exists' => 'Subkontraktor yang dipilih tidak ditemukan.',
            'agreed_amount.min' => 'Nilai kontrak tidak boleh negatif.',
            'actual_amount.min' => 'Nilai aktual tidak boleh negatif.',
            'retention_percent.max' => 'Persentase retensi tidak boleh lebih dari 100%.',
            'scheduled_end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'work_order_id.exists' => 'Work order yang dipilih tidak ditemukan.',
            'project_id.exists' => 'Proyek yang dipilih tidak ditemukan.',
        ];
    }
}
