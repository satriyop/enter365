<?php

declare(strict_types=1);

namespace App\Domain\Sales\DeliveryOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\DeliveryOrder;

class DeliveryOrderStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private DeliveryOrder $deliveryOrder;

    public function __construct(
        DocumentStatus $initialStatus,
        DeliveryOrder $deliveryOrder,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->deliveryOrder = $deliveryOrder;
    }

    public static function fromDeliveryOrder(
        DeliveryOrder $deliveryOrder,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($deliveryOrder->status, $deliveryOrder, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Confirmed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Confirmed->value => [
                DocumentStatus::Shipped->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Shipped->value => [
                DocumentStatus::Delivered->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'delivery_order_id' => $this->deliveryOrder->id,
            'do_number' => $this->deliveryOrder->do_number,
            'customer_id' => $this->deliveryOrder->contact_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->deliveryOrder->status = $status;
        $this->deliveryOrder->save();
    }

    protected function getDocumentType(): string
    {
        return 'Delivery Order';
    }

    protected function getDocumentId(): int
    {
        return $this->deliveryOrder->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return Events\DeliveryOrderStatusChanged::class;
    }

    public function canConfirm(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->deliveryOrder->items()->exists();
    }

    public function canShip(): bool
    {
        return $this->currentStatus === DocumentStatus::Confirmed;
    }

    public function canDeliver(): bool
    {
        return $this->currentStatus === DocumentStatus::Shipped;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Confirmed,
        ], true);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->canEdit();
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Confirmed && ! $this->deliveryOrder->items()->exists()) {
            throw new \InvalidArgumentException('Delivery Order tidak dapat dikonfirmasi karena tidak memiliki item.');
        }
    }

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->updateTimestamps($from, $to);
    }

    private function updateTimestamps(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $updates = [];

        switch ($to) {
            case DocumentStatus::Confirmed:
                $updates['confirmed_at'] = now();
                $updates['confirmed_by'] = $userId;
                break;
            case DocumentStatus::Shipped:
                $updates['shipped_at'] = now();
                $updates['shipped_by'] = $this->context['shipped_by'] ?? $userId;
                $updates['shipping_date'] = $this->context['shipping_date'] ?? now()->toDateString();
                break;
            case DocumentStatus::Delivered:
                $updates['delivered_at'] = now();
                $updates['delivered_by'] = $userId;
                $updates['received_date'] = $this->context['received_date'] ?? now()->toDateString();
                break;
            case DocumentStatus::Cancelled:
                $updates['cancelled_at'] = now();
                $updates['cancelled_by'] = $userId;
                $updates['cancellation_reason'] = $this->context['cancellation_reason'] ?? '';
                break;
        }

        if (! empty($updates)) {
            $this->deliveryOrder->update($updates);
        }
    }

    protected function beforeConfirmed(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\DeliveryOrderConfirmed(
            $this->deliveryOrder->id,
            $this->deliveryOrder->do_number,
            $this->deliveryOrder->contact_id,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function beforeShipped(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\DeliveryOrderShipped(
            $this->deliveryOrder->id,
            $this->deliveryOrder->do_number,
            $this->deliveryOrder->contact_id,
            $this->deliveryOrder->shipping_method ?? 'courier',
            $this->deliveryOrder->tracking_number,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function beforeDelivered(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\DeliveryOrderDelivered(
            $this->deliveryOrder->id,
            $this->deliveryOrder->do_number,
            $this->deliveryOrder->contact_id,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $reason = $this->context['cancellation_reason'] ?? '';
        $this->eventDispatcher->dispatch(new Events\DeliveryOrderCancelled(
            $this->deliveryOrder->id,
            $this->deliveryOrder->do_number,
            $this->deliveryOrder->contact_id,
            $reason,
            $this->getContextUserId(),
            now()
        ));
    }
}
