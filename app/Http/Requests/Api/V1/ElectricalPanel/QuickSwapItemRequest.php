<?php

namespace App\Http\Requests\Api\V1\ElectricalPanel;

use Illuminate\Foundation\Http\FormRequest;

class QuickSwapItemRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
