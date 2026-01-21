<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\Events\EventDispatcherInterface;
use App\Infrastructure\Events\RecordingEventDispatcher;

/**
 * Trait for testing domain events.
 *
 * Provides assertion methods for verifying domain events
 * were dispatched during test execution.
 *
 * Usage in Pest:
 * ```php
 * uses(InteractsWithDomainEvents::class);
 *
 * beforeEach(function () {
 *     $this->setUpDomainEvents();
 * });
 *
 * it('dispatches InvoiceSent event', function () {
 *     // ... perform action
 *     $this->assertDomainEventDispatched(InvoiceSent::class);
 * });
 * ```
 */
trait InteractsWithDomainEvents
{
    protected RecordingEventDispatcher $domainEvents;

    /**
     * Set up domain event recording.
     * Call this in beforeEach().
     */
    protected function setUpDomainEvents(): void
    {
        $this->domainEvents = new RecordingEventDispatcher;
        $this->app->instance(EventDispatcherInterface::class, $this->domainEvents);
    }

    /**
     * Assert that a domain event was dispatched.
     *
     * @param  callable|null  $callback  Optional callback to verify event properties
     */
    protected function assertDomainEventDispatched(string $eventClass, ?callable $callback = null): void
    {
        $this->domainEvents->assertDispatched($eventClass, $callback);
    }

    /**
     * Assert that a domain event was NOT dispatched.
     */
    protected function assertDomainEventNotDispatched(string $eventClass): void
    {
        $this->domainEvents->assertNotDispatched($eventClass);
    }

    /**
     * Assert that no domain events were dispatched.
     */
    protected function assertNoDomainEventsDispatched(): void
    {
        $this->domainEvents->assertNothingDispatched();
    }

    /**
     * Get the count of dispatched events for a specific class.
     */
    protected function getDomainEventCount(string $eventClass): int
    {
        return $this->domainEvents->dispatchCount($eventClass);
    }

    /**
     * Get all dispatched events of a specific class.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function getDispatchedDomainEvents(?string $eventClass = null): \Illuminate\Support\Collection
    {
        return $this->domainEvents->dispatched($eventClass);
    }

    /**
     * Reset all recorded domain events.
     */
    protected function resetDomainEvents(): void
    {
        $this->domainEvents->reset();
    }
}
