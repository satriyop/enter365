<?php

declare(strict_types=1);

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// Create a concrete implementation for testing
class TestStateMachine extends AbstractStateMachine
{
    private int $documentId = 1;

    private array $statusHistory = [];

    private bool $guardShouldPass = true;

    private bool $actionWasExecuted = false;

    public function setGuardShouldPass(bool $pass): void
    {
        $this->guardShouldPass = $pass;
    }

    public function wasActionExecuted(): bool
    {
        return $this->actionWasExecuted;
    }

    public function getRecordedHistory(): array
    {
        return $this->statusHistory;
    }

    protected function registerGuards(): void
    {
        $this->addGuard(
            DocumentStatus::Draft->value,
            DocumentStatus::Confirmed->value,
            fn () => ['passes' => $this->guardShouldPass, 'message' => 'Guard failed']
        );
    }

    protected function registerActions(): void
    {
        $this->addAction(
            DocumentStatus::Draft->value,
            DocumentStatus::Confirmed->value,
            fn () => $this->actionWasExecuted = true
        );
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Confirmed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Confirmed->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::InProgress->value => [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Completed->value => [],
            DocumentStatus::Cancelled->value => [],
        ];
    }

    protected function getContextData(): array
    {
        return ['test' => 'data'];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        // Mock persistence
    }

    protected function getDocumentType(): string
    {
        return 'Test';
    }

    protected function getDocumentId(): int
    {
        return $this->documentId;
    }

    protected function getStatusChangedEvent(): string
    {
        return TestStatusChangedEvent::class;
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->statusHistory[] = [
            'from' => $from->value,
            'to' => $to->value,
        ];
    }
}

class TestStatusChangedEvent
{
    public function __construct(
        public int $documentId,
        public DocumentStatus $from,
        public DocumentStatus $to,
        public ?int $userId
    ) {}
}

describe('AbstractStateMachine', function () {
    beforeEach(function () {
        $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $this->eventDispatcher->shouldReceive('dispatch')->byDefault();

        $this->stateMachine = new TestStateMachine(
            DocumentStatus::Draft,
            $this->eventDispatcher
        );
    });

    describe('transitions', function () {
        it('returns current status', function () {
            expect($this->stateMachine->getCurrentStatus())->toBe(DocumentStatus::Draft);
        });

        it('validates allowed transitions', function () {
            expect($this->stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeTrue();
            expect($this->stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
            expect($this->stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeFalse();
        });

        it('returns next valid statuses', function () {
            $nextStatuses = $this->stateMachine->getNextValidStatuses();

            expect($nextStatuses)->toContain(DocumentStatus::Confirmed->value);
            expect($nextStatuses)->toContain(DocumentStatus::Cancelled->value);
        });

        it('performs valid transition', function () {
            $this->stateMachine->transitionTo(DocumentStatus::Confirmed);

            expect($this->stateMachine->getCurrentStatus())->toBe(DocumentStatus::Confirmed);
        });

        it('throws exception for invalid transition', function () {
            $this->stateMachine->transitionTo(DocumentStatus::Completed);
        })->throws(StateTransitionException::class);

        it('rejects same status as invalid transition', function () {
            // A "transition" must change state - staying in same status is not a transition
            expect($this->stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        });
    });

    describe('guards', function () {
        it('prevents transition when guard fails', function () {
            $this->stateMachine->setGuardShouldPass(false);

            expect($this->stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeFalse();
        });

        it('allows transition when guard passes', function () {
            $this->stateMachine->setGuardShouldPass(true);

            expect($this->stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeTrue();
        });

        it('returns guard failure reasons', function () {
            $this->stateMachine->setGuardShouldPass(false);

            $reasons = $this->stateMachine->getGuardFailureReasons(DocumentStatus::Confirmed);

            expect($reasons)->toContain('Guard failed');
        });

        it('filters next valid statuses based on guards', function () {
            $this->stateMachine->setGuardShouldPass(false);

            $nextStatuses = $this->stateMachine->getNextValidStatuses();

            expect($nextStatuses)->not->toContain(DocumentStatus::Confirmed->value);
            expect($nextStatuses)->toContain(DocumentStatus::Cancelled->value);
        });
    });

    describe('actions', function () {
        it('executes action after successful transition', function () {
            $this->stateMachine->transitionTo(DocumentStatus::Confirmed);

            expect($this->stateMachine->wasActionExecuted())->toBeTrue();
        });

        it('does not execute action if guard fails', function () {
            $this->stateMachine->setGuardShouldPass(false);

            try {
                $this->stateMachine->transitionTo(DocumentStatus::Confirmed);
            } catch (StateTransitionException) {
                // Expected
            }

            expect($this->stateMachine->wasActionExecuted())->toBeFalse();
        });
    });

    describe('history', function () {
        it('records history on transition', function () {
            $this->stateMachine->transitionTo(DocumentStatus::Confirmed);

            $history = $this->stateMachine->getRecordedHistory();

            expect($history)->toHaveCount(1);
            expect($history[0]['from'])->toBe(DocumentStatus::Draft->value);
            expect($history[0]['to'])->toBe(DocumentStatus::Confirmed->value);
        });
    });

    describe('workflow metadata', function () {
        it('returns workflow metadata', function () {
            $metadata = $this->stateMachine->getWorkflowMetadata();

            expect($metadata)->toHaveKeys(['current_status', 'next_statuses', 'all_statuses', 'transitions']);
            expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        });

        it('includes current status details', function () {
            $metadata = $this->stateMachine->getWorkflowMetadata();

            expect($metadata['current_status'])->toHaveKeys(['value', 'label', 'color', 'is_terminal']);
        });

        it('includes next statuses with labels', function () {
            $metadata = $this->stateMachine->getWorkflowMetadata();

            expect($metadata['next_statuses'])->toBeArray();
            foreach ($metadata['next_statuses'] as $status) {
                expect($status)->toHaveKeys(['value', 'label', 'color']);
            }
        });

        it('includes transition map', function () {
            $metadata = $this->stateMachine->getWorkflowMetadata();

            expect($metadata['transitions'])->toBeArray();
            foreach ($metadata['transitions'] as $transition) {
                expect($transition)->toHaveKeys(['from', 'to', 'available']);
            }
        });

        it('marks current transitions as available when guards pass', function () {
            $metadata = $this->stateMachine->getWorkflowMetadata();

            $currentTransitions = array_filter(
                $metadata['transitions'],
                fn ($t) => $t['from'] === DocumentStatus::Draft->value
            );

            $confirmedTransition = array_values(array_filter(
                $currentTransitions,
                fn ($t) => $t['to'] === DocumentStatus::Confirmed->value
            ))[0] ?? null;

            expect($confirmedTransition)->not->toBeNull();
            expect($confirmedTransition['available'])->toBeTrue();
        });

        it('marks transitions as unavailable when guards fail', function () {
            $this->stateMachine->setGuardShouldPass(false);

            $metadata = $this->stateMachine->getWorkflowMetadata();

            $confirmedTransition = array_values(array_filter(
                $metadata['transitions'],
                fn ($t) => $t['from'] === DocumentStatus::Draft->value && $t['to'] === DocumentStatus::Confirmed->value
            ))[0] ?? null;

            expect($confirmedTransition)->not->toBeNull();
            expect($confirmedTransition['available'])->toBeFalse();
        });
    });

    describe('events', function () {
        it('dispatches event on transition', function () {
            $this->eventDispatcher->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::type(TestStatusChangedEvent::class));

            $this->stateMachine->transitionTo(DocumentStatus::Confirmed);
        });
    });

    describe('context', function () {
        it('passes context to transition', function () {
            $this->stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => 123]);

            expect($this->stateMachine->getCurrentStatus())->toBe(DocumentStatus::Confirmed);
        });
    });
});
