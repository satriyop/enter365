<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contactId = $this->route('contact')?->id ?? $this->route('contact');

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('contacts', 'code')->ignore($contactId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in([Contact::TYPE_CUSTOMER, Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['integer', 'min:0'],
            'payment_term_days' => ['integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }
}
