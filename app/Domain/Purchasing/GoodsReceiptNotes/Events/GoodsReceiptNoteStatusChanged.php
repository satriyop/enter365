<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\GoodsReceiptNotes\Events;

use App\Enums\DocumentStatus;

readonly class GoodsReceiptNoteStatusChanged
{
    public function __construct(
        public int $grnId,
        public DocumentStatus $fromStatus,
        public DocumentStatus $toStatus,
        public ?int $userId = null
    ) {}
}
