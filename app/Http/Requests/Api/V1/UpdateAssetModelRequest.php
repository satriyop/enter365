<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AssetModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetModelRequest extends FormRequest
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
        $model = $this->route('asset_model');
        $modelId = is_object($model) ? $model->id : $model;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('asset_models', 'code')->ignore($modelId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'method' => ['sometimes', 'string', Rule::in(AssetModel::methods())],
            'method_number' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'method_period' => ['sometimes', 'string', Rule::in(AssetModel::periods())],
            'method_progress_factor' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'salvage_value_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'asset_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'expense_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
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
            'code.unique' => 'Kode model aset sudah digunakan.',
        ];
    }
}
