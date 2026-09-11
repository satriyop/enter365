<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('payment_provider');

        return $record instanceof PaymentProvider
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', PaymentProvider::class);
    }

    public function rules(): array
    {
        $record = $this->route('payment_provider');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'filled', 'string', 'max:64', Rule::unique('payment_providers', 'code')->ignore($record)],
            'name' => [$required, 'filled', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'state' => [$required, Rule::in(['disabled', 'test', 'enabled'])],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'journal_id' => ['nullable', 'integer', Rule::exists('journals', 'id')->where('type', 'bank')],
            'payment_method_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'payment_method_ids.*' => ['required', 'integer', 'distinct', Rule::exists('payment_methods', 'id')->where('direction', 'inbound')],
        ];
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
