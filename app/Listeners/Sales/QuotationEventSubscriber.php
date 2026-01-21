<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Domain\Sales\Quotations\Events\QuotationCancelled;
use App\Domain\Sales\Quotations\Events\QuotationConverted;
use App\Domain\Sales\Quotations\Events\QuotationExpired;
use App\Domain\Sales\Quotations\Events\QuotationLost;
use App\Domain\Sales\Quotations\Events\QuotationRejected;
use App\Domain\Sales\Quotations\Events\QuotationStatusChanged;
use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Domain\Sales\Quotations\Events\QuotationWon;
use Illuminate\Events\Dispatcher;

/**
 * Handles all quotation-related events.
 */
class QuotationEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(QuotationStatusChanged $event): void
    {
        $this->logger->logOperation('quotation.status_changed', [
            'quotation_id' => $event->quotationId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleSubmitted(QuotationSubmitted $event): void
    {
        $this->logger->logOperation('quotation.submitted', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'customer_id' => $event->customerId,
            'total_amount' => $event->totalAmount,
            'submitted_at' => $event->submittedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleApproved(QuotationApproved $event): void
    {
        $this->logger->logOperation('quotation.approved', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'approved_at' => $event->approvedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleRejected(QuotationRejected $event): void
    {
        $this->logger->logOperation('quotation.rejected', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'reason' => $event->reason,
            'rejected_at' => $event->rejectedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(QuotationCancelled $event): void
    {
        $this->logger->logOperation('quotation.cancelled', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'previous_status' => $event->previousStatus->value,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleConverted(QuotationConverted $event): void
    {
        $this->logger->logOperation('quotation.converted', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'invoice_id' => $event->invoiceId,
            'converted_at' => $event->convertedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleExpired(QuotationExpired $event): void
    {
        $this->logger->logOperation('quotation.expired', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'valid_until' => $event->validUntil->toDateString(),
            'expired_at' => $event->expiredAt->toIso8601String(),
        ]);
    }

    public function handleWon(QuotationWon $event): void
    {
        $this->logger->logOperation('quotation.won', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'total_amount' => $event->totalAmount,
            'won_at' => $event->wonAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleLost(QuotationLost $event): void
    {
        $this->logger->logOperation('quotation.lost', [
            'quotation_id' => $event->quotationId,
            'quotation_number' => $event->quotationNumber,
            'reason' => $event->reason,
            'lost_at' => $event->lostAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            QuotationStatusChanged::class => 'handleStatusChanged',
            QuotationSubmitted::class => 'handleSubmitted',
            QuotationApproved::class => 'handleApproved',
            QuotationRejected::class => 'handleRejected',
            QuotationCancelled::class => 'handleCancelled',
            QuotationConverted::class => 'handleConverted',
            QuotationExpired::class => 'handleExpired',
            QuotationWon::class => 'handleWon',
            QuotationLost::class => 'handleLost',
        ];
    }
}
