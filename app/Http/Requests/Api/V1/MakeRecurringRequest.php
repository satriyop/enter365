<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\RecurringTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MakeRecurringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:200'],
            'frequency' => ['required', Rule::in([
                RecurringTemplate::FREQUENCY_DAILY,
                RecurringTemplate::FREQUENCY_WEEKLY,
                RecurringTemplate::FREQUENCY_MONTHLY,
                RecurringTemplate::FREQUENCY_QUARTERLY,
                RecurringTemplate::FREQUENCY_YEARLY,
            ])],
            'interval' => ['integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'occurrences_limit' => ['nullable', 'integer', 'min:1'],
            'auto_post' => ['boolean'],
            'auto_send' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'frequency.required' => 'Frekuensi wajib dipilih.',
            'frequency.in' => 'Frekuensi tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.after' => 'Tanggal akhir harus setelah tanggal mulai.',
        ];
    }
}
