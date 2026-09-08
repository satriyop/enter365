<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContactAddressRole;
use App\Models\Contacts\Contact;
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
        $contact = $this->route('contact');
        $contactId = $contact->id ?? $contact;

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('contacts', 'code')->ignore($contactId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in([Contact::TYPE_CUSTOMER, Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])],
            'is_company' => ['boolean'],
            'parent_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('is_company', true), Rule::notIn([(int) $contactId])],
            'address_role' => ['nullable', 'string', Rule::enum(ContactAddressRole::class)],
            'job_position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'size:2'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'is_pkp' => ['boolean'],
            'nik' => ['nullable', 'string', 'max:20'],

            // Payment terms
            'credit_limit' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'payment_term_days' => ['integer', 'min:0', 'max:365'],
            'early_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'early_discount_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            // Bank account details
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],

            // Subcontractor fields
            'is_subcontractor' => ['boolean'],
            'subcontractor_services' => ['nullable', 'array'],
            'subcontractor_services.*' => ['string', 'max:100'],
            'hourly_rate' => ['nullable', 'integer', 'min:0'],
            'daily_rate' => ['nullable', 'integer', 'min:0'],

            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
