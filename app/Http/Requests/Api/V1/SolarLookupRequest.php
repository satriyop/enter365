<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SolarLookupRequest extends FormRequest
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
            'latitude' => ['required_with:longitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'nullable', 'numeric', 'between:-180,180'],
            'max_distance_km' => ['nullable', 'numeric', 'min:1'],
            'province' => ['required_without_all:latitude,longitude', 'nullable', 'string', 'max:100'],
            'city' => ['required_with:province', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'province.required_without_all' => 'Harap berikan province+city atau latitude+longitude.',
        ];
    }

    /**
     * Determine the lookup mode based on validated input.
     */
    public function isCoordinateLookup(): bool
    {
        return $this->has('latitude') && $this->has('longitude');
    }
}
