<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Enums\DocumentStatus;

class InvoiceStatusChanged
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
