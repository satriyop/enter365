<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\ProductVendorPricelist
 */
class ProductVendorPricelistResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     contact_id: int,
     *     contact?: array{id: int, name: string, code: string|null},
     *     min_qty: float,
     *     unit: string,
     *     price: int,
     *     currency: string,
     *     lead_time_days: int,
     *     vendor_product_code: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'code' => $this->contact->code,
            ]),
            'min_qty' => (float) $this->min_qty,
            'unit' => $this->unit,
            'price' => $this->price,
            'currency' => $this->currency,
            'lead_time_days' => $this->lead_time_days,
            'vendor_product_code' => $this->vendor_product_code,
        ];
    }
}
