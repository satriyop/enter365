<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;
use Illuminate\Support\Facades\Event;

/**
 * Production event dispatcher that delegates to Laravel's Event facade.
 */
class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        Event::dispatch($event);
    }
}
