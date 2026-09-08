<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AnalyticAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnalyticAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var AnalyticAccount|int|string|null $account */
        $account = $this->route('analytic_account');
        $ignoreId = $account instanceof AnalyticAccount ? $account->id : $account;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('analytic_accounts', 'code')->ignore($ignoreId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode akun analitik wajib diisi.',
            'code.unique' => 'Kode akun analitik sudah digunakan.',
            'name.required' => 'Nama akun analitik wajib diisi.',
        ];
    }
}
