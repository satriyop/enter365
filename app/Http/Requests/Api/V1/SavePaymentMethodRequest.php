<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('payment_method');

        return $record instanceof PaymentMethod
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', PaymentMethod::class);
    }

    public function rules(): array
    {
        $record = $this->route('payment_method');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'filled', 'string', 'max:64', Rule::unique('payment_methods', 'code')->ignore($record)],
            'name' => [$required, 'filled', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'direction' => [$required, Rule::in(['inbound', 'outbound'])],
            'payment_type' => [$required, Rule::in(['manual', 'bank_transfer', 'check', 'card', 'cash'])],
            'journal_id' => ['nullable', 'integer', Rule::exists('journals', 'id')->whereIn('type', ['bank', 'cash'])],
        ];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            $record = $this->route('payment_method');
            if ($record instanceof PaymentMethod && $this->input('direction') === 'outbound' && $record->paymentProviders()->exists()) {
                $validator->errors()->add('direction', 'Remove this method from its providers before changing it to outgoing payments.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This code is already in use.',
            'name.required' => 'Enter a name for this configuration.',
            'journal_id.exists' => 'Select a journal with the correct bank or cash type.',
        ];
    }
}
