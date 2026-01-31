<?php

declare(strict_types=1);

namespace App\Listeners\Purchasing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\GoodsReceiptNotes\Events\GoodsReceiptNoteStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all goods receipt note-related events.
 */
class GoodsReceiptNoteEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(GoodsReceiptNoteStatusChanged $event): void
    {
        $this->logger->logOperation('goods_receipt_note.status_changed', [
            'grn_id' => $event->grnId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            GoodsReceiptNoteStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
