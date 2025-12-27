<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\SubcontractorWorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubcontractorWorkOrderRequest extends FormRequest
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
            'subcontractor_id' => ['required', 'integer', 'exists:contacts,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scope_of_work' => ['nullable', 'string', 'max:5000'],
            'agreed_amount' => ['required', 'integer', 'min:0'],
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
            'subcontractor_id.required' => 'Subkontraktor harus dipilih.',
            'subcontractor_id.exists' => 'Subkontraktor yang dipilih tidak ditemukan.',
            'name.required' => 'Nama pekerjaan harus diisi.',
            'agreed_amount.required' => 'Nilai kontrak harus diisi.',
            'agreed_amount.min' => 'Nilai kontrak tidak boleh negatif.',
            'retention_percent.max' => 'Persentase retensi tidak boleh lebih dari 100%.',
            'scheduled_end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'work_order_id.exists' => 'Work order yang dipilih tidak ditemukan.',
            'project_id.exists' => 'Proyek yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('retention_percent')) {
            $this->merge([
                'retention_percent' => SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT,
            ]);
        }
    }
}
