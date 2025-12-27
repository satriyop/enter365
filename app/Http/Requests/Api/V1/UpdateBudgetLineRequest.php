<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annual_amount' => ['nullable', 'integer', 'min:0'],
            'distribute_evenly' => ['nullable', 'boolean'],
            'jan_amount' => ['nullable', 'integer', 'min:0'],
            'feb_amount' => ['nullable', 'integer', 'min:0'],
            'mar_amount' => ['nullable', 'integer', 'min:0'],
            'apr_amount' => ['nullable', 'integer', 'min:0'],
            'may_amount' => ['nullable', 'integer', 'min:0'],
            'jun_amount' => ['nullable', 'integer', 'min:0'],
            'jul_amount' => ['nullable', 'integer', 'min:0'],
            'aug_amount' => ['nullable', 'integer', 'min:0'],
            'sep_amount' => ['nullable', 'integer', 'min:0'],
            'oct_amount' => ['nullable', 'integer', 'min:0'],
            'nov_amount' => ['nullable', 'integer', 'min:0'],
            'dec_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
