<?php

namespace App\Http\Requests\Api\V1\Solar;

use Illuminate\Foundation\Http\FormRequest;

class SolarCitiesRequest extends FormRequest
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
            'province' => ['required', 'string', 'max:100'],
        ];
    }
}
