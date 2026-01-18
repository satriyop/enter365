<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;

/**
 * Test-friendly event dispatcher that captures events without side effects.
 *
 * Use this in tests to verify events were dispatched without needing
 * Laravel's Event::fake() which affects all tests.
 */
class NullEventDispatcher implements EventDispatcherInterface
{
    /** @var array<int, object> Events that have been dispatched */
    private array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    /**
     * Check if an event of the given class was dispatched.
     */
    public function hasDispatched(string $eventClass): bool
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all dispatched events of a given class.
     *
     * @return array<int, object>
     */
    public function getDispatched(string $eventClass): array
    {
        return array_filter(
            $this->dispatched,
            fn (object $event) => $event instanceof $eventClass
        );
    }

    /**
     * Get all dispatched events.
     *
     * @return array<int, object>
     */
    public function all(): array
    {
        return $this->dispatched;
    }

    /**
     * Get the count of dispatched events.
     */
    public function count(): int
    {
        return count($this->dispatched);
    }

    /**
     * Clear all dispatched events.
     */
    public function clear(): void
    {
        $this->dispatched = [];
    }

    /**
     * Assert that an event of the given class was dispatched.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertDispatched(string $eventClass): void
    {
        if (! $this->hasDispatched($eventClass)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected event [{$eventClass}] was not dispatched."
            );
        }
    }

    /**
     * Assert that an event of the given class was NOT dispatched.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertNotDispatched(string $eventClass): void
    {
        if ($this->hasDispatched($eventClass)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Unexpected event [{$eventClass}] was dispatched."
            );
        }
    }
}
