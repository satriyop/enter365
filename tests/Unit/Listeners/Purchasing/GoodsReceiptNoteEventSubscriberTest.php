<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\GoodsReceiptNotes\Events\GoodsReceiptNoteStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Purchasing\GoodsReceiptNoteEventSubscriber;
use Illuminate\Events\Dispatcher;

describe('GoodsReceiptNoteEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new GoodsReceiptNoteEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to goods receipt note status changed event', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(GoodsReceiptNoteStatusChanged::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change from draft to confirmed', function () {
            $event = new GoodsReceiptNoteStatusChanged(
                grnId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Confirmed,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('goods_receipt_note.status_changed', [
                    'grn_id' => 1,
                    'from' => 'draft',
                    'to' => 'confirmed',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });

        it('logs status change to received', function () {
            $event = new GoodsReceiptNoteStatusChanged(
                grnId: 2,
                fromStatus: DocumentStatus::Confirmed,
                toStatus: DocumentStatus::Completed,
                userId: 15,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('goods_receipt_note.status_changed', [
                    'grn_id' => 2,
                    'from' => 'confirmed',
                    'to' => 'completed',
                    'user_id' => 15,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });
});
