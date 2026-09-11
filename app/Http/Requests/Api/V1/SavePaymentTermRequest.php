<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\PaymentTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('payment_term');

        return $record instanceof PaymentTerm
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', PaymentTerm::class);
    }

    public function rules(): array
    {
        $record = $this->route('payment_term');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'filled', 'string', 'max:64', Rule::unique('payment_terms', 'code')->ignore($record)],
            'name' => [$required, 'filled', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => [$required, 'array', 'list', 'min:1', 'max:36'],
            'lines.*' => ['required', 'array:type,value,days,due_type'],
            'lines.*.type' => ['required', Rule::in(['percent', 'fixed', 'balance'])],
            'lines.*.value' => ['required', 'numeric', 'min:0', 'max:1000000000000', 'decimal:0,2'],
            'lines.*.days' => ['required', 'integer', 'min:0', 'max:3650'],
            'lines.*.due_type' => ['required', Rule::in(['days_after', 'end_of_month', 'end_of_next_month'])],
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

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('lines')) {
                return;
            }

            $lines = $this->input('lines');
            $balanceCount = 0;
            $percent = 0;
            foreach ($lines as $index => $line) {
                if ($line['type'] === 'balance') {
                    $balanceCount++;
                    if ($index !== count($lines) - 1 || (float) $line['value'] !== 0.0) {
                        $validator->errors()->add('lines', 'The remaining balance must be the final line with value zero.');
                    }
                } elseif ((float) $line['value'] <= 0) {
                    $validator->errors()->add("lines.$index.value", 'Installment values must be positive.');
                }
                if ($line['type'] === 'percent') {
                    $percent += (int) round((float) $line['value'] * 100);
                }
            }
            if ($balanceCount !== 1 || $percent >= 10000) {
                $validator->errors()->add('lines', 'Use one final balance line and percentages totaling less than 100%.');
            }
        }];
    }
}
