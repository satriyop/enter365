<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ClosePosSessionRequest extends FormRequest
{
    use AuthorizesPosPermission;

    protected function requiredPosPermission(): string
    {
        return 'pos.session.close';
    }

    protected function unauthorizedPosMessage(): string
    {
        return 'Anda tidak boleh menutup sesi kasir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash_amount' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counted_cash_amount.required' => 'Hasil hitung kas wajib diisi.',
        ];
    }
}
