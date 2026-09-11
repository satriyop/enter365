<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AssetModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:30', 'unique:fixed_assets,code'],
            'name' => ['required', 'string', 'max:200'],
            'asset_model_id' => ['nullable', 'integer', 'exists:asset_models,id'],
            'original_value' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'integer', 'min:0'],
            'acquisition_date' => ['required', 'date'],
            'method' => ['required_without:asset_model_id', 'string', Rule::in(AssetModel::methods())],
            'method_number' => ['required_without:asset_model_id', 'integer', 'min:1', 'max:600'],
            'method_period' => ['nullable', 'string', Rule::in(AssetModel::periods())],
            'method_progress_factor' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'asset_account_id' => ['required_without:asset_model_id', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['required_without:asset_model_id', 'integer', 'exists:accounts,id'],
            'expense_account_id' => ['required_without:asset_model_id', 'integer', 'exists:accounts,id'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode aset wajib diisi.',
            'code.unique' => 'Kode aset sudah digunakan.',
            'name.required' => 'Nama aset wajib diisi.',
            'original_value.required' => 'Nilai perolehan wajib diisi.',
            'acquisition_date.required' => 'Tanggal perolehan wajib diisi.',
            'method.required_without' => 'Metode penyusutan wajib diisi jika model aset tidak dipilih.',
            'method_number.required_without' => 'Jumlah periode wajib diisi jika model aset tidak dipilih.',
            'asset_account_id.required_without' => 'Akun aset wajib dipilih jika model aset tidak dipilih.',
        ];
    }
}
