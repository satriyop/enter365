<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowUpLevelRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:200'],
            'delay_days' => ['sometimes', 'integer'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'send_email' => ['sometimes', 'boolean'],
            'join_invoices' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
