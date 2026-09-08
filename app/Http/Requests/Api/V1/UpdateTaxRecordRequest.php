<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Tax\TaxRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tax = $this->route('tax_record');
        $taxId = is_object($tax) ? $tax->id : $tax;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('tax_records', 'code')->ignore($taxId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'computation' => ['nullable', 'string', Rule::in([TaxRecord::COMPUTATION_PERCENTAGE])],
            'applicability' => ['sometimes', Rule::in(TaxRecord::applicabilities())],
            'is_active' => ['boolean'],
            'invoice_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'refund_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'tax_tag_id' => ['nullable', 'integer', 'exists:tax_tags,id'],
        ];
    }
}
