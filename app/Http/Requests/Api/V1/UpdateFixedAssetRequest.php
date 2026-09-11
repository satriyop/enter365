<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AssetModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFixedAssetRequest extends FormRequest
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
        $asset = $this->route('fixed_asset');
        $assetId = is_object($asset) ? $asset->id : $asset;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('fixed_assets', 'code')->ignore($assetId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'asset_model_id' => ['nullable', 'integer', 'exists:asset_models,id'],
            'original_value' => ['sometimes', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'integer', 'min:0'],
            'acquisition_date' => ['sometimes', 'date'],
            'method' => ['sometimes', 'string', Rule::in(AssetModel::methods())],
            'method_number' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'method_period' => ['sometimes', 'string', Rule::in(AssetModel::periods())],
            'method_progress_factor' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'asset_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'expense_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
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
            'code.unique' => 'Kode aset sudah digunakan.',
        ];
    }
}
