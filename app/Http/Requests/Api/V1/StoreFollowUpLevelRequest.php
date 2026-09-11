<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowUpLevelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'delay_days' => ['required', 'integer'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'send_email' => ['sometimes', 'boolean'],
            'join_invoices' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama tingkat follow-up wajib diisi.',
            'delay_days.required' => 'Hari jatuh tempo wajib diisi.',
        ];
    }
}
