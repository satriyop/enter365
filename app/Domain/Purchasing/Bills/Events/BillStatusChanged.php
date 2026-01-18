<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Enums\DocumentStatus;

class BillStatusChanged
{
    public function __construct(
        public readonly int $billId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public function toArray(): array
    {
        return [
            'bill_id' => $this->billId,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
