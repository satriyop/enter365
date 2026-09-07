<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && $this->input('currency') === '') {
            $this->merge(['currency' => null]);
        }
    }

    public function rules(): array
    {
        $account = $this->route('account');
        $accountId = $account->id ?? $account;

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($accountId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(Account::getTypes())],
            'subtype' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
            'allow_reconciliation' => ['boolean'],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::in(config('accounting.multi_currency.supported_currencies', [])),
            ],
        ];
    }

    /**
     * Validate parent account type compatibility.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parentId = $this->input('parent_id');
            if (! $parentId) {
                return;
            }

            $parent = Account::find($parentId);
            if (! $parent) {
                return;
            }

            $account = $this->route('account');
            $type = $this->input('type', $account->type ?? null);

            if ($type && $parent->type !== $type) {
                $validator->errors()->add(
                    'parent_id',
                    'Tipe akun induk harus sama dengan tipe akun.'
                );
            }
        });
    }
}
