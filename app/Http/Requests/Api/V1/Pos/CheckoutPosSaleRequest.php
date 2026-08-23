<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutPosSaleRequest extends FormRequest
{
    use AuthorizesPosPermission;

    protected function requiredPosPermission(): string
    {
        return 'pos.sale.checkout';
    }

    protected function unauthorizedPosMessage(): string
    {
        return 'Anda tidak boleh menyelesaikan penjualan kasir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'way' => ['required', 'in:cash,qris'],
            'cash_received_amount' => ['required_if:way,cash', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'way.required' => 'Cara bayar wajib diisi.',
            'way.in' => 'Cara bayar harus tunai atau QRIS.',
            'lines.required' => 'Pesanan wajib diisi.',
            'Idempotency-Key.required' => 'Idempotency-Key wajib diisi.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->header('Idempotency-Key') === null || $this->header('Idempotency-Key') === '') {
                $validator->errors()->add('Idempotency-Key', 'Idempotency-Key wajib diisi.');
            }
        });
    }
}
