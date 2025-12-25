<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', Rule::in(array_keys(Attachment::getCategories()))],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File wajib diunggah.',
            'file.max' => 'Ukuran file maksimal 10MB.',
            'attachable_type.required' => 'Tipe dokumen wajib diisi.',
            'attachable_id.required' => 'ID dokumen wajib diisi.',
            'category.in' => 'Kategori tidak valid.',
        ];
    }
}
