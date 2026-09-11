<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeferredEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $entry = $this->route('deferredEntry');
        $entryId = is_object($entry) ? $entry->id : $entry;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('deferred_entries', 'code')->ignore($entryId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'start_date' => ['sometimes', 'date'],
            'deferred_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'recognition_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'counterpart_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Kode entri tangguhan sudah digunakan.',
        ];
    }
}
