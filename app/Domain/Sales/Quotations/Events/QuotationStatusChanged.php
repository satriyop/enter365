<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Enums\DocumentStatus;

class QuotationStatusChanged
{
    public function __construct(
        public readonly int $quotationId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public function toArray(): array
    {
        return [
            'quotation_id' => $this->quotationId,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
        ];
    }
}
