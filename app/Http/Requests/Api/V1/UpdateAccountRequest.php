<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'opening_balance' => ['integer'],
        ];
    }
}
