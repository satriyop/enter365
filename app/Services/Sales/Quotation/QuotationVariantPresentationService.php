<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Domain\Sales\Quotations\QuotationDomainFactory;
use App\Http\Resources\Api\V1\QuotationVariantOptionResource;
use App\Models\Sales\Quotation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Builds API presentation payloads for multi-option quotation variants.
 */
class QuotationVariantPresentationService
{
    public function __construct(
        private QuotationDomainFactory $domainFactory,
    ) {}

    /**
     * Build the variant options payload for a multi-option quotation.
     *
     * @return array{
     *     options: AnonymousResourceCollection,
     *     meta: array{
     *         quotation_id: int,
     *         quotation_number: string,
     *         variant_group_id: int|null,
     *         selected_variant_id: int|null,
     *         has_selected_variant: bool
     *     }
     * }
     */
    public function getVariantOptionsPayload(Quotation $quotation): array
    {
        $options = $quotation->variantOptions()
            ->with('bom')
            ->orderBy('sort_order')
            ->get();

        return [
            'options' => QuotationVariantOptionResource::collection($options),
            'meta' => [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->getFullNumber(),
                'variant_group_id' => $quotation->variant_group_id,
                'selected_variant_id' => $quotation->selected_variant_id,
                'has_selected_variant' => $quotation->hasSelectedVariant(),
            ],
        ];
    }

    /**
     * Build the customer-facing variant comparison payload.
     *
     * @return array{
     *     quotation: array{
     *         id: int,
     *         quotation_number: string,
     *         subject: string|null,
     *         contact: array{id: int, name: string}|null,
     *         selected_variant_id: int|null
     *     },
     *     options: array<int, array<string, mixed>>,
     *     price_range: array{min: int, max: int, difference: int}|null
     * }
     */
    public function getVariantComparisonPayload(Quotation $quotation): array
    {
        $quotation->loadMissing(['contact', 'variantOptions']);

        $comparison = $this->domainFactory->getVariantComparison($quotation);

        return [
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->getFullNumber(),
                'subject' => $quotation->subject,
                'contact' => $quotation->contact ? [
                    'id' => $quotation->contact->id,
                    'name' => $quotation->contact->name,
                ] : null,
                'selected_variant_id' => $quotation->selected_variant_id,
            ],
            'options' => $comparison['options'] ?? [],
            'price_range' => $comparison['price_range'] ?? null,
        ];
    }
}
