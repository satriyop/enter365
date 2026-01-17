<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Sales\Quotation;

interface QuotationNumberGeneratorInterface
{
    public function generateQuotationNumber(): string;

    public function getNextRevisionNumber(Quotation $quotation): int;

    public function getFullNumber(Quotation $quotation): string;
}
