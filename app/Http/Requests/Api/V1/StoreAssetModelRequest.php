<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AssetModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('is_active')) {
            $this->merge(['is_active' => true]);
        }
        if (! $this->exists('method')) {
            $this->merge(['method' => AssetModel::METHOD_LINEAR]);
        }
        if (! $this->exists('method_period')) {
            $this->merge(['method_period' => AssetModel::PERIOD_MONTH]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:asset_models,code'],
            'name' => ['required', 'string', 'max:200'],
            'method' => ['required', 'string', Rule::in(AssetModel::methods())],
            'method_number' => ['required', 'integer', 'min:1', 'max:600'],
            'method_period' => ['required', 'string', Rule::in(AssetModel::periods())],
            'method_progress_factor' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'salvage_value_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'asset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode model aset wajib diisi.',
            'code.unique' => 'Kode model aset sudah digunakan.',
            'name.required' => 'Nama model aset wajib diisi.',
            'method_number.required' => 'Jumlah periode penyusutan wajib diisi.',
            'asset_account_id.required' => 'Akun aset wajib dipilih.',
            'depreciation_account_id.required' => 'Akun akumulasi penyusutan wajib dipilih.',
            'expense_account_id.required' => 'Akun beban penyusutan wajib dipilih.',
        ];
    }
}
