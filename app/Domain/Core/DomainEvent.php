<?php

declare(strict_types=1);

namespace App\Domain\Core;

use DateTimeImmutable;
use ReflectionClass;

/**
 * Base class for domain events.
 *
 * Provides common functionality:
 * - Timestamp tracking (when the event occurred)
 * - Serialization to array
 * - Event name derivation
 *
 * New events should extend this class.
 * Existing events can be gradually migrated.
 */
abstract readonly class DomainEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    /**
     * Get event name for logging/tracking.
     *
     * Converts class name to snake_case.
     * Example: InvoicePaid -> invoice_paid
     */
    public function getEventName(): string
    {
        $className = (new ReflectionClass($this))->getShortName();

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Convert event to array for serialization/logging.
     */
    abstract public function toArray(): array;

    /**
     * Get base array with common event metadata.
     *
     * @return array<string, mixed>
     */
    protected function baseToArray(): array
    {
        return [
            'event_name' => $this->getEventName(),
            'occurred_at' => $this->occurredAt->format('c'),
        ];
    }
}
