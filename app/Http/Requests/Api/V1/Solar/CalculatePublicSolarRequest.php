<?php

namespace App\Http\Requests\Api\V1\Solar;

use App\Models\Solar\PlnTariff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatePublicSolarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monthly_bill' => ['required', 'numeric', 'min:5000000', 'max:2000000000'],
            'pln_power_va' => ['nullable', 'integer', 'min:5500', 'max:10000000'],
            'pln_category' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('pln_tariffs', 'category_code')->where(function ($query): void {
                    $query->where('is_active', true)
                        ->whereIn('customer_type', [
                            PlnTariff::TYPE_BUSINESS,
                            PlnTariff::TYPE_INDUSTRIAL,
                        ]);
                }),
            ],
            'target_savings' => ['nullable', 'numeric', 'min:0', 'max:2000000000'],
            'price_per_kwp' => ['nullable', 'numeric', 'min:8000000', 'max:25000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_bill.required' => 'Tagihan listrik bulanan wajib diisi.',
            'monthly_bill.min' => 'Tagihan listrik bulanan minimal Rp 5.000.000.',
            'monthly_bill.max' => 'Tagihan listrik bulanan melebihi batas.',
            'pln_category.exists' => 'Kategori PLN harus tarif bisnis atau industri yang aktif.',
            'price_per_kwp.min' => 'Harga per kWp di luar rentang yang diizinkan.',
            'price_per_kwp.max' => 'Harga per kWp di luar rentang yang diizinkan.',
        ];
    }
}
