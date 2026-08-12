<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Projects\ProjectRevenue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRevenueRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_keys(ProjectRevenue::getRevenueTypes()))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
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
            'type.required' => 'Tipe pendapatan harus diisi.',
            'description.required' => 'Deskripsi pendapatan harus diisi.',
            'amount.required' => 'Jumlah pendapatan harus diisi.',
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
        $validated['revenue_type'] = $validated['type'] ?? null;
        $validated['revenue_date'] = $validated['date'] ?? null;

        return $validated;
    }
}
