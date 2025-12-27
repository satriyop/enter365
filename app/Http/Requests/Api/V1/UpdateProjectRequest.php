<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'contract_amount' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'string', Rule::in(array_keys(Project::getPriorities()))],
            'location' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
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
            'name.required' => 'Nama proyek harus diisi.',
            'contact_id.required' => 'Pelanggan harus dipilih.',
            'contact_id.exists' => 'Pelanggan yang dipilih tidak ditemukan.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }
}
