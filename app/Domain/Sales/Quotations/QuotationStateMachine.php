<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Enums\DocumentStatus;

readonly class QuotationStateMachine
{
    private const VALID_TRANSITIONS = [
        DocumentStatus::Draft->value => [
            DocumentStatus::Submitted->value,
        ],
        DocumentStatus::Submitted->value => [
            DocumentStatus::Approved->value,
            DocumentStatus::Rejected->value,
        ],
        DocumentStatus::Approved->value => [
            DocumentStatus::Converted->value,
            DocumentStatus::Expired->value,
        ],
        DocumentStatus::Rejected->value => [
            DocumentStatus::Draft->value, // Revision
        ],
    ];

    public function __construct(
        private DocumentStatus $currentStatus,
        private bool $hasItems,
        private bool $isExpired,
        private ?int $convertedInvoiceId,
    ) {}

    public static function fromQuotation(
        DocumentStatus $status,
        bool $hasItems,
        \DateTimeInterface $validUntil,
        ?int $convertedInvoiceId = null
    ): self {
        return new self(
            $status,
            $hasItems,
            self::checkExpired($status, $validUntil),
            $convertedInvoiceId
        );
    }

    private static function checkExpired(DocumentStatus $status, \DateTimeInterface $validUntil): bool
    {
        if ($status === DocumentStatus::Expired) {
            return true;
        }

        return \Carbon\Carbon::parse($validUntil)->isPast();
    }

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->hasItems;
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted
            && ! $this->isExpired;
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canConvert(): bool
    {
        return $this->currentStatus === DocumentStatus::Approved
            && $this->convertedInvoiceId === null;
    }

    public function canRevise(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Expired,
        ], true);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canMarkAsWon(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ], true);
    }

    public function canMarkAsLost(): bool
    {
        return $this->canMarkAsWon();
    }

    public function canDelete(): bool
    {
        return $this->canEdit();
    }

    public function getNextValidStatuses(): array
    {
        return self::VALID_TRANSITIONS[$this->currentStatus->value] ?? [];
    }

    public function getStatus(): DocumentStatus
    {
        return $this->currentStatus;
    }

    public function isExpired(): bool
    {
        return $this->isExpired;
    }

    public function hasItems(): bool
    {
        return $this->hasItems;
    }

    public function getConvertedInvoiceId(): ?int
    {
        return $this->convertedInvoiceId;
    }
}
