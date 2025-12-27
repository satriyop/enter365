<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Bom;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $bom_id
 * @property int $contact_id
 * @property float|null $margin_percent
 * @property int|null $selling_price
 * @property bool|null $expand_items
 * @property string|null $quotation_date
 * @property string|null $valid_until
 * @property string|null $subject
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $terms_conditions
 * @property float|null $tax_rate
 * @property string|null $currency
 * @property float|null $exchange_rate
 */
class StoreQuotationFromBomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bom_id' => [
                'required',
                'integer',
                'exists:boms,id',
                function ($attribute, $value, $fail) {
                    $bom = Bom::find($value);
                    if ($bom && $bom->status !== Bom::STATUS_ACTIVE) {
                        $fail('BOM harus berstatus aktif.');
                    }
                },
            ],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'selling_price' => ['nullable', 'integer', 'min:0'],
            'expand_items' => ['nullable', 'boolean'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'subject' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bom_id.required' => 'BOM harus dipilih.',
            'bom_id.exists' => 'BOM tidak ditemukan.',
            'contact_id.required' => 'Pelanggan harus dipilih.',
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'margin_percent.min' => 'Margin tidak boleh negatif.',
            'margin_percent.max' => 'Margin maksimal 500%.',
            'selling_price.min' => 'Harga jual tidak boleh negatif.',
            'valid_until.after_or_equal' => 'Tanggal berlaku harus sama atau setelah tanggal penawaran.',
        ];
    }
}
