<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\QuotationActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationActivityRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                Rule::in([
                    QuotationActivity::TYPE_CALL,
                    QuotationActivity::TYPE_EMAIL,
                    QuotationActivity::TYPE_MEETING,
                    QuotationActivity::TYPE_NOTE,
                    QuotationActivity::TYPE_WHATSAPP,
                    QuotationActivity::TYPE_VISIT,
                ]),
            ],
            'contact_method' => [
                'nullable',
                'string',
                Rule::in([
                    QuotationActivity::METHOD_PHONE,
                    QuotationActivity::METHOD_WHATSAPP,
                    QuotationActivity::METHOD_EMAIL,
                    QuotationActivity::METHOD_VISIT,
                ]),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'activity_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], // max 24 hours
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'follow_up_type' => [
                'nullable',
                'string',
                Rule::in([
                    QuotationActivity::TYPE_CALL,
                    QuotationActivity::TYPE_EMAIL,
                    QuotationActivity::TYPE_MEETING,
                    QuotationActivity::TYPE_VISIT,
                ]),
            ],
            'outcome' => [
                'nullable',
                'string',
                Rule::in([
                    QuotationActivity::OUTCOME_POSITIVE,
                    QuotationActivity::OUTCOME_NEUTRAL,
                    QuotationActivity::OUTCOME_NEGATIVE,
                    QuotationActivity::OUTCOME_NO_ANSWER,
                ]),
            ],
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
            'type.required' => 'Tipe aktivitas harus diisi.',
            'type.in' => 'Tipe aktivitas tidak valid.',
            'activity_at.required' => 'Tanggal aktivitas harus diisi.',
            'activity_at.date' => 'Format tanggal aktivitas tidak valid.',
            'duration_minutes.min' => 'Durasi minimal 1 menit.',
            'duration_minutes.max' => 'Durasi maksimal 24 jam.',
            'next_follow_up_at.after' => 'Tanggal follow-up harus di masa depan.',
        ];
    }
}
