<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Contracts\Services\Domains\QuotationNumberGeneratorInterface;
use App\Models\Sales\Quotation;

class QuotationNumberGenerator implements QuotationNumberGeneratorInterface
{
    public function __construct(
        private DocumentNumberGeneratorInterface $documentNumberGenerator
    ) {}

    public function generateQuotationNumber(): string
    {
        return $this->documentNumberGenerator->generate(
            'QUO-'.now()->format('Ym').'-',
            'quotations',
            'quotation_number'
        );
    }

    public function getNextRevisionNumber(Quotation $quotation): int
    {
        $originalId = $quotation->original_quotation_id ?? $quotation->id;

        return (Quotation::query()
            ->where('id', $originalId)
            ->orWhere('original_quotation_id', $originalId)
            ->max('revision') ?? 0) + 1;
    }

    public function getFullNumber(Quotation $quotation): string
    {
        if ($quotation->revision > 0) {
            return "{$quotation->quotation_number}-R{$quotation->revision}";
        }

        return $quotation->quotation_number;
    }
}
