<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Projects\ProjectCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectCostRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_keys(ProjectCost::getCostTypes()))],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'type.required' => 'Tipe biaya harus diisi.',
            'description.required' => 'Deskripsi biaya harus diisi.',
            'unit_cost.required' => 'Biaya satuan harus diisi.',
        ];
    }

    /**
     * Get the validated data with API fields remapped to model columns.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        /** @var array<string, mixed> $validated */
        $validated['cost_type'] = $validated['type'] ?? null;
        $validated['cost_date'] = $validated['date'] ?? null;

        return $validated;
    }
}
