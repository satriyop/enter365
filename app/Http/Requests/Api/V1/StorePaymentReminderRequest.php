<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Shared\PaymentReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'type' => [
                'required',
                Rule::in([
                    PaymentReminder::TYPE_UPCOMING,
                    PaymentReminder::TYPE_OVERDUE,
                    PaymentReminder::TYPE_FINAL_NOTICE,
                ]),
            ],
            'channel' => [
                'required',
                Rule::in([
                    PaymentReminder::CHANNEL_EMAIL,
                    PaymentReminder::CHANNEL_WHATSAPP,
                ]),
            ],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_date.required' => 'Tanggal pengingat harus diisi.',
            'scheduled_date.after_or_equal' => 'Tanggal pengingat harus hari ini atau setelahnya.',
            'type.required' => 'Tipe pengingat harus diisi.',
            'type.in' => 'Tipe pengingat tidak valid.',
            'channel.required' => 'Channel pengiriman harus diisi.',
            'channel.in' => 'Channel pengiriman tidak valid.',
        ];
    }
}
