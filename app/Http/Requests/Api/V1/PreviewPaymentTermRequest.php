<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('payment_term'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000000'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return ['amount.min' => 'Enter a positive amount in minor currency units.'];
    }
}
