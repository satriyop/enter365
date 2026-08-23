<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

use Illuminate\Foundation\Http\FormRequest;

class VoidPosSaleRequest extends FormRequest
{
    use AuthorizesPosPermission;

    protected function requiredPosPermission(): string
    {
        return 'pos.sale.void';
    }

    protected function unauthorizedPosMessage(): string
    {
        return 'Anda tidak boleh membatalkan penjualan kasir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
        ];
    }
}
