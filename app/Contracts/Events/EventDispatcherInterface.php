<?php

declare(strict_types=1);

namespace App\Contracts\Events;

/**
 * Contract for event dispatching, enabling dependency injection and testability.
 *
 * This abstraction allows state machines and domain services to dispatch events
 * through an injectable interface rather than using the Event facade directly,
 * enabling isolated unit testing without Laravel's Event::fake().
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param  object  $event  The event instance to dispatch
     */
    public function dispatch(object $event): void;
}
