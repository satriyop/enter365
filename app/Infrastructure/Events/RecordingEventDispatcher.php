<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Event dispatcher that records events for testing.
 *
 * Use in tests to verify events were dispatched without
 * actually triggering listeners.
 */
class RecordingEventDispatcher implements EventDispatcherInterface
{
    private Collection $events;

    private bool $shouldDispatch;

    public function __construct(bool $shouldDispatch = false)
    {
        $this->events = collect();
        $this->shouldDispatch = $shouldDispatch;
    }

    public function dispatch(object $event): void
    {
        $this->events->push([
            'event' => $event,
            'class' => get_class($event),
            'time' => now(),
        ]);

        if ($this->shouldDispatch) {
            event($event);
        }
    }

    /**
     * Get all recorded events.
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * Get events of specific type.
     */
    public function getEventsOfType(string $eventClass): Collection
    {
        return $this->events->filter(fn ($e) => $e['class'] === $eventClass);
    }

    /**
     * Assert event was dispatched.
     *
     * @throws AssertionFailedError
     */
    public function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        $events = $this->getEventsOfType($eventClass);

        if ($events->isEmpty()) {
            throw new AssertionFailedError(
                "Event [{$eventClass}] was not dispatched."
            );
        }

        if ($callback !== null) {
            $matching = $events->filter(fn ($e) => $callback($e['event']));

            if ($matching->isEmpty()) {
                throw new AssertionFailedError(
                    "Event [{$eventClass}] was dispatched but callback returned false."
                );
            }
        }
    }

    /**
     * Assert event was not dispatched.
     *
     * @throws AssertionFailedError
     */
    public function assertNotDispatched(string $eventClass): void
    {
        $events = $this->getEventsOfType($eventClass);

        if ($events->isNotEmpty()) {
            throw new AssertionFailedError(
                "Event [{$eventClass}] was dispatched but should not have been."
            );
        }
    }

    /**
     * Assert no events were dispatched.
     *
     * @throws AssertionFailedError
     */
    public function assertNothingDispatched(): void
    {
        if ($this->events->isNotEmpty()) {
            $classes = $this->events->pluck('class')->unique()->implode(', ');

            throw new AssertionFailedError(
                "Events were dispatched: [{$classes}]"
            );
        }
    }

    /**
     * Get count of dispatched events.
     */
    public function getDispatchedCount(?string $eventClass = null): int
    {
        if ($eventClass === null) {
            return $this->events->count();
        }

        return $this->getEventsOfType($eventClass)->count();
    }

    /**
     * Get the first dispatched event of a type.
     */
    public function getFirstEvent(string $eventClass): ?object
    {
        $events = $this->getEventsOfType($eventClass);

        return $events->first()['event'] ?? null;
    }

    /**
     * Clear recorded events.
     */
    public function clear(): void
    {
        $this->events = collect();
    }

    /**
     * Alias for clear() - matches Laravel Event fake API.
     */
    public function reset(): void
    {
        $this->clear();
    }

    /**
     * Get dispatched events (optionally filtered by class).
     * Matches Laravel Event fake API.
     */
    public function dispatched(?string $eventClass = null): Collection
    {
        if ($eventClass === null) {
            return $this->events->pluck('event');
        }

        return $this->getEventsOfType($eventClass)->pluck('event');
    }

    /**
     * Get count of dispatched events by class.
     * Matches Laravel Event fake API.
     */
    public function dispatchCount(string $eventClass): int
    {
        return $this->getEventsOfType($eventClass)->count();
    }

    /**
     * Set whether to also dispatch events to real handlers.
     */
    public function shouldDispatch(bool $should): self
    {
        $this->shouldDispatch = $should;

        return $this;
    }
}
