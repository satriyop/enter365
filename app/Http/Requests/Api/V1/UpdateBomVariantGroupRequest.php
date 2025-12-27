<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\BomVariantGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomVariantGroupRequest extends FormRequest
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
            'comparison_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([
                BomVariantGroup::STATUS_DRAFT,
                BomVariantGroup::STATUS_ACTIVE,
                BomVariantGroup::STATUS_ARCHIVED,
            ])],
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
            'name.required' => 'Nama variant group harus diisi.',
            'name.max' => 'Nama variant group maksimal 255 karakter.',
        ];
    }
}
