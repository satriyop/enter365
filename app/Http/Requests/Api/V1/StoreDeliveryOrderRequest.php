<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\DeliveryOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryOrderRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'do_date' => ['required', 'date'],
            'shipping_date' => ['nullable', 'date', 'after_or_equal:do_date'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_method' => ['nullable', Rule::in(DeliveryOrder::SHIPPING_METHODS)],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
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
            'contact_id.required' => 'Customer is required.',
            'contact_id.exists' => 'Selected customer does not exist.',
            'do_date.required' => 'Delivery order date is required.',
            'shipping_date.after_or_equal' => 'Shipping date must be on or after the DO date.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.description.required' => 'Item description is required.',
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.min' => 'Item quantity must be greater than 0.',
        ];
    }
}
