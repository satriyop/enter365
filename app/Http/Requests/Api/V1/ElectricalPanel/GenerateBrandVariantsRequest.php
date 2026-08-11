<?php

namespace App\Http\Requests\Api\V1\ElectricalPanel;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBrandVariantsRequest extends FormRequest
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
            'brands' => ['required', 'array', 'min:1'],
            'brands.*' => ['string'],
            'group_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
