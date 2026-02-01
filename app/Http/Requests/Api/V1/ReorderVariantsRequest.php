<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReorderVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array'],
            'order.*.bom_id' => ['required', 'integer', 'exists:boms,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
