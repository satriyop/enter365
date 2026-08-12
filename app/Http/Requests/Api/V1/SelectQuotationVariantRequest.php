<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Sales\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SelectQuotationVariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Check multi-option type before field validation for clearer API errors.
     */
    protected function prepareForValidation(): void
    {
        $quotation = $this->route('quotation');

        if ($quotation instanceof Quotation && ! $quotation->isMultiOption()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Penawaran ini bukan tipe multi-option.',
            ], 422));
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant_option_id' => ['required', 'exists:quotation_variant_options,id'],
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
            'variant_option_id.required' => 'Pilihan varian harus dipilih.',
            'variant_option_id.exists' => 'Pilihan varian tidak ditemukan.',
        ];
    }
}
