<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Events;

use App\Infrastructure\Events\RecordingEventDispatcher;
use PHPUnit\Framework\AssertionFailedError;

// Using stdClass-based events for testing
function createEventA(int $id): object
{
    $event = new \stdClass;
    $event->id = $id;
    $event->type = 'event_a';

    return $event;
}

function createEventB(string $name): object
{
    $event = new \stdClass;
    $event->name = $name;
    $event->type = 'event_b';

    return $event;
}

describe('RecordingEventDispatcher', function () {
    beforeEach(function () {
        $this->dispatcher = new RecordingEventDispatcher;
    });

    describe('dispatch', function () {
        it('records dispatched events', function () {
            $event = createEventA(1);

            $this->dispatcher->dispatch($event);

            expect($this->dispatcher->getDispatchedCount())->toBe(1);
        });

        it('records multiple events', function () {
            $this->dispatcher->dispatch(createEventA(1));
            $this->dispatcher->dispatch(createEventB('test'));

            expect($this->dispatcher->getDispatchedCount())->toBe(2);
        });
    });

    describe('assertDispatched', function () {
        it('passes when event was dispatched', function () {
            $this->dispatcher->dispatch(createEventA(1));

            // Should not throw
            $this->dispatcher->assertDispatched(\stdClass::class);

            expect(true)->toBeTrue();
        });

        it('fails when event was not dispatched', function () {
            expect(fn () => $this->dispatcher->assertDispatched(\stdClass::class))
                ->toThrow(AssertionFailedError::class);
        });

        it('passes callback validation when event matches', function () {
            $event = createEventA(42);
            $this->dispatcher->dispatch($event);

            $this->dispatcher->assertDispatched(\stdClass::class, function ($e) {
                return $e->id === 42;
            });

            expect(true)->toBeTrue();
        });

        it('fails when callback returns false', function () {
            $this->dispatcher->dispatch(createEventA(42));

            expect(fn () => $this->dispatcher->assertDispatched(\stdClass::class, function ($e) {
                return $e->id === 999;
            }))->toThrow(AssertionFailedError::class);
        });
    });

    describe('assertNotDispatched', function () {
        it('passes when event was not dispatched', function () {
            $this->dispatcher->assertNotDispatched(\stdClass::class);

            expect(true)->toBeTrue();
        });

        it('fails when event was dispatched', function () {
            $this->dispatcher->dispatch(createEventA(1));

            expect(fn () => $this->dispatcher->assertNotDispatched(\stdClass::class))
                ->toThrow(AssertionFailedError::class);
        });
    });

    describe('assertNothingDispatched', function () {
        it('passes when no events were dispatched', function () {
            $this->dispatcher->assertNothingDispatched();

            expect(true)->toBeTrue();
        });

        it('fails when any event was dispatched', function () {
            $this->dispatcher->dispatch(createEventA(1));

            expect(fn () => $this->dispatcher->assertNothingDispatched())
                ->toThrow(AssertionFailedError::class);
        });
    });

    describe('dispatched', function () {
        it('returns collection of dispatched events by class', function () {
            $this->dispatcher->dispatch(createEventA(1));
            $this->dispatcher->dispatch(createEventA(2));

            $events = $this->dispatcher->dispatched(\stdClass::class);

            expect($events)->toHaveCount(2);
        });

        it('returns all events when no class specified', function () {
            $this->dispatcher->dispatch(createEventA(1));
            $this->dispatcher->dispatch(createEventB('test'));

            $events = $this->dispatcher->dispatched();

            expect($events)->toHaveCount(2);
        });
    });

    describe('reset', function () {
        it('clears all recorded events', function () {
            $this->dispatcher->dispatch(createEventA(1));
            $this->dispatcher->dispatch(createEventB('test'));

            $this->dispatcher->reset();

            $this->dispatcher->assertNothingDispatched();
        });
    });

    describe('shouldDispatch', function () {
        it('returns self for method chaining', function () {
            $result = $this->dispatcher->shouldDispatch(true);

            expect($result)->toBe($this->dispatcher);
        });
    });

    describe('dispatchCount', function () {
        it('returns count of dispatched events by class', function () {
            $this->dispatcher->dispatch(createEventA(1));
            $this->dispatcher->dispatch(createEventA(2));

            expect($this->dispatcher->dispatchCount(\stdClass::class))->toBe(2);
        });

        it('returns zero for undispatched event class', function () {
            expect($this->dispatcher->dispatchCount(\stdClass::class))->toBe(0);
        });
    });

    describe('getFirstEvent', function () {
        it('returns the first event of a type', function () {
            $event1 = createEventA(1);
            $event2 = createEventA(2);

            $this->dispatcher->dispatch($event1);
            $this->dispatcher->dispatch($event2);

            $first = $this->dispatcher->getFirstEvent(\stdClass::class);

            expect($first)->toBe($event1);
        });

        it('returns null when no events of type exist', function () {
            $first = $this->dispatcher->getFirstEvent(\stdClass::class);

            expect($first)->toBeNull();
        });
    });
});
