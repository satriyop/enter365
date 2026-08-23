<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosSessionRequest extends FormRequest
{
    use AuthorizesPosPermission;

    protected function requiredPosPermission(): string
    {
        return 'pos.session.open';
    }

    protected function unauthorizedPosMessage(): string
    {
        return 'Anda tidak boleh membuka sesi kasir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opening_cash_amount' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'opening_cash_amount.required' => 'Uang modal wajib diisi.',
        ];
    }
}
