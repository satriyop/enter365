<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pos;

use Illuminate\Foundation\Http\FormRequest;

class HoldPosCartRequest extends FormRequest
{
    use AuthorizesPosPermission;

    protected function requiredPosPermission(): string
    {
        return 'pos.sale.checkout';
    }

    protected function unauthorizedPosMessage(): string
    {
        return 'Anda tidak boleh menahan pesanan kasir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
