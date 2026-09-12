<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRatingRequest extends FormRequest
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
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'rateable_type' => ['nullable', 'string', 'in:task,project'],
            'rateable_id' => ['nullable', 'integer', 'required_with:rateable_type'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'rated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Proyek wajib dipilih.',
            'project_id.exists' => 'Proyek tidak ditemukan.',
            'rating.required' => 'Nilai rating wajib diisi.',
            'rating.min' => 'Nilai rating harus antara 1 dan 5.',
            'rating.max' => 'Nilai rating harus antara 1 dan 5.',
            'rateable_type.in' => 'Tipe rating tidak valid.',
            'rateable_id.required_with' => 'ID objek rating wajib diisi.',
            'contact_id.exists' => 'Kontak tidak ditemukan.',
        ];
    }
}
