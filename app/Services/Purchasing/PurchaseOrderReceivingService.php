<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\Base\BaseService;

/**
 * Service for purchase order receiving status management.
 *
 * Handles receiving status transitions and progress tracking.
 */
class PurchaseOrderReceivingService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Update receiving status based on items.
     *
     * Uses state machine for status transitions to ensure proper
     * event dispatch and timestamp management.
     */
    public function updateReceivingStatus(PurchaseOrder $po): PurchaseOrder
    {
        $stateMachine = $po->stateMachine();

        if (! $stateMachine->canReceive()) {
            return $po;
        }

        return $this->executeInTransaction('update_receiving_status', function () use ($po) {
            $isFullyReceived = $po->isFullyReceived();
            $hasReceivedItems = $po->hasReceivedItems();

            // Determine target status based on receiving state
            if ($isFullyReceived && $po->status !== DocumentStatus::Received) {
                // Fully received - transition to Received
                // State machine handles fully_received_at timestamp
                $po->transitionTo(DocumentStatus::Received, $this->getUserId());
            } elseif ($hasReceivedItems && $po->status === DocumentStatus::Approved) {
                // Partial receive - transition to Partial
                // State machine handles first_received_at timestamp
                $po->transitionTo(DocumentStatus::Partial, $this->getUserId());
            }

            return $po->fresh();
        }, ['purchase_order_id' => $po->id]);
    }

    /**
     * Check if purchase order can receive items.
     */
    public function canReceive(PurchaseOrder $po): bool
    {
        return $po->canReceive();
    }

    /**
     * Get receiving summary for a purchase order.
     *
     * @return array{status: \App\Enums\DocumentStatus, progress: float, is_fully_received: bool, has_received_items: bool, first_received_at: string|null, fully_received_at: string|null}
     */
    public function getReceivingSummary(PurchaseOrder $po): array
    {
        return [
            'status' => $po->status,
            'progress' => $po->getReceivingProgress(),
            'is_fully_received' => $po->isFullyReceived(),
            'has_received_items' => $po->hasReceivedItems(),
            'first_received_at' => $po->first_received_at?->toIso8601String(),
            'fully_received_at' => $po->fully_received_at?->toIso8601String(),
        ];
    }

    /**
     * Get items with receiving status.
     *
     * @return array<int, array{item_id: int, product_name: string, ordered: float, received: float, remaining: float, is_complete: bool}>
     */
    public function getItemsReceivingStatus(PurchaseOrder $po): array
    {
        $po->load(['items.product']);

        return $po->items->map(fn ($item) => [
            'item_id' => $item->id,
            'product_name' => $item->product?->name ?? $item->description,
            'ordered' => (float) $item->quantity,
            'received' => (float) $item->quantity_received,
            'remaining' => (float) max(0, $item->quantity - $item->quantity_received),
            'is_complete' => $item->quantity_received >= $item->quantity,
        ])->toArray();
    }
}
