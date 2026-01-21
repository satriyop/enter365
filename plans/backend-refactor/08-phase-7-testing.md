# Phase 7: Testing Infrastructure

> **Goal**: Improve test coverage, testing utilities, and test organization.

## Current State

- Feature tests exist for API endpoints
- Some domain tests for state machines
- Factory support for models
- Pest testing framework

---

## Deliverables

- [ ] Test helpers and utilities
- [ ] In-memory repository testing
- [ ] Event assertion helpers
- [ ] Database seeding for tests
- [ ] Performance testing foundation

---

## Part 1: Test Helpers

### 1.1 Test Case Traits

```php
<?php
// File: tests/Traits/InteractsWithDomainEvents.php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\Events\EventDispatcherInterface;
use App\Infrastructure\Events\RecordingEventDispatcher;

trait InteractsWithDomainEvents
{
    protected RecordingEventDispatcher $domainEvents;

    protected function setUpDomainEvents(): void
    {
        $this->domainEvents = new RecordingEventDispatcher();
        $this->app->instance(EventDispatcherInterface::class, $this->domainEvents);
    }

    protected function assertDomainEventDispatched(string $eventClass, ?callable $callback = null): void
    {
        $this->domainEvents->assertDispatched($eventClass, $callback);
    }

    protected function assertDomainEventNotDispatched(string $eventClass): void
    {
        $this->domainEvents->assertNotDispatched($eventClass);
    }

    protected function assertNoDomainEventsDispatched(): void
    {
        $this->domainEvents->assertNothingDispatched();
    }
}
```

```php
<?php
// File: tests/Traits/InteractsWithRepositories.php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Infrastructure\Repositories\InMemory\InMemoryInvoiceRepository;

trait InteractsWithRepositories
{
    protected InMemoryInvoiceRepository $invoiceRepository;

    protected function setUpInMemoryRepositories(): void
    {
        $this->invoiceRepository = new InMemoryInvoiceRepository();
        $this->app->instance(InvoiceRepositoryInterface::class, $this->invoiceRepository);
    }

    protected function seedInvoices(array $invoices): void
    {
        $this->invoiceRepository->seed($invoices);
    }
}
```

### 1.2 Service Test Helper

```php
<?php
// File: tests/Traits/TestsServices.php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;

trait TestsServices
{
    protected User $testUser;

    protected function setUpServiceTests(): void
    {
        $this->testUser = User::factory()->create();
        $this->actingAs($this->testUser);
    }

    protected function assertServiceResult($result, bool $expectSuccess = true): void
    {
        if ($expectSuccess) {
            expect($result->isSuccess())->toBeTrue($result->getMessage() ?? 'Expected success');
        } else {
            expect($result->isFailure())->toBeTrue('Expected failure');
        }
    }
}
```

---

## Part 2: Domain Service Tests

### 2.1 Invoice Service Test

```php
<?php
// File: tests/Feature/Services/Sales/InvoiceServiceTest.php

declare(strict_types=1);

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use Tests\Traits\InteractsWithDomainEvents;
use Tests\Traits\TestsServices;

uses(InteractsWithDomainEvents::class, TestsServices::class);

beforeEach(function () {
    $this->setUpDomainEvents();
    $this->setUpServiceTests();
    $this->service = app(InvoiceServiceInterface::class);
});

describe('InvoiceService::create', function () {

    it('creates invoice with items', function () {
        $contact = Contact::factory()->create();

        $result = $this->service->create([
            'contact_id' => $contact->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'description' => 'Product A',
                    'quantity' => 2,
                    'unit_price' => 100000,
                ],
                [
                    'description' => 'Product B',
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $this->assertServiceResult($result);
        $invoice = $result->getDataOrFail();

        expect($invoice->items)->toHaveCount(2);
        expect($invoice->subtotal)->toBe(250000);
        expect($invoice->status)->toBe(DocumentStatus::Draft);
    });

    it('generates invoice number automatically', function () {
        $contact = Contact::factory()->create();

        $result = $this->service->create([
            'contact_id' => $contact->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 10000]],
        ]);

        $invoice = $result->getDataOrFail();
        expect($invoice->invoice_number)->toStartWith('INV-');
    });
});

describe('InvoiceService::post', function () {

    it('posts invoice and dispatches event', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2))
            ->draft()
            ->create();

        $result = $this->service->post($invoice);

        $this->assertServiceResult($result);
        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
        $this->assertDomainEventDispatched(InvoiceSent::class);
    });

    it('fails to post invoice without items', function () {
        $invoice = Invoice::factory()->draft()->create();

        expect(fn () => $this->service->post($invoice))
            ->toThrow(StateTransitionException::class);
    });

    it('fails to post already sent invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->sent()
            ->create();

        expect(fn () => $this->service->post($invoice))
            ->toThrow(StateTransitionException::class);
    });
});

describe('InvoiceService::update', function () {

    it('updates draft invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();

        $result = $this->service->update($invoice, [
            'description' => 'Updated description',
        ]);

        $this->assertServiceResult($result);
        expect($invoice->fresh()->description)->toBe('Updated description');
    });

    it('fails to update sent invoice', function () {
        $invoice = Invoice::factory()->sent()->create();

        expect(fn () => $this->service->update($invoice, ['description' => 'Test']))
            ->toThrow(\App\Exceptions\Domain\DocumentLockedException::class);
    });
});

describe('InvoiceService::delete', function () {

    it('deletes draft invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();
        $invoiceId = $invoice->id;

        $result = $this->service->delete($invoice);

        $this->assertServiceResult($result);
        expect(Invoice::find($invoiceId))->toBeNull();
    });

    it('fails to delete sent invoice', function () {
        $invoice = Invoice::factory()->sent()->create();

        expect(fn () => $this->service->delete($invoice))
            ->toThrow(\App\Exceptions\Domain\DocumentLockedException::class);
    });
});
```

---

## Part 3: State Machine Tests

```php
<?php
// File: tests/Unit/Domain/StateMachine/InvoiceStateMachineTest.php

declare(strict_types=1);

use App\Domain\Sales\Invoices\InvoiceStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\NullEventDispatcher;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;

describe('InvoiceStateMachine', function () {

    it('allows draft to sent transition when has items', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect($sm->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('blocks draft to sent when no items', function () {
        $invoice = Invoice::factory()->draft()->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect($sm->canTransitionTo(DocumentStatus::Sent))->toBeFalse();
    });

    it('returns correct next valid statuses', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());
        $nextStatuses = $sm->getNextValidStatuses();

        expect($nextStatuses)->toContain(DocumentStatus::Sent);
        expect($nextStatuses)->toContain(DocumentStatus::Cancelled);
    });

    it('transitions and persists status', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());
        $sm->transitionTo(DocumentStatus::Sent);

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('throws on invalid transition', function () {
        $invoice = Invoice::factory()->cancelled()->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect(fn () => $sm->transitionTo(DocumentStatus::Sent))
            ->toThrow(StateTransitionException::class);
    });

    it('provides workflow metadata', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory())
            ->draft()
            ->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());
        $metadata = $sm->getWorkflowMetadata();

        expect($metadata)->toHaveKey('current_status');
        expect($metadata)->toHaveKey('next_statuses');
        expect($metadata)->toHaveKey('all_statuses');
        expect($metadata)->toHaveKey('transitions');
        expect($metadata['current_status']['value'])->toBe('draft');
    });
});
```

---

## Part 4: Repository Tests

```php
<?php
// File: tests/Unit/Repositories/EloquentInvoiceRepositoryTest.php

declare(strict_types=1);

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;

describe('EloquentInvoiceRepository', function () {

    beforeEach(function () {
        $this->repository = app(InvoiceRepositoryInterface::class);
    });

    it('finds invoice by id', function () {
        $invoice = Invoice::factory()->create();

        $found = $this->repository->find($invoice->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($invoice->id);
    });

    it('finds invoices by status', function () {
        Invoice::factory()->count(3)->draft()->create();
        Invoice::factory()->count(2)->sent()->create();

        $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

        expect($drafts)->toHaveCount(3);
    });

    it('finds overdue invoices', function () {
        Invoice::factory()->sent()->create(['due_date' => now()->subDays(5)]);
        Invoice::factory()->sent()->create(['due_date' => now()->addDays(5)]);

        $overdue = $this->repository->findOverdue();

        expect($overdue)->toHaveCount(1);
    });

    it('calculates outstanding for contact', function () {
        $contact = Contact::factory()->create();

        Invoice::factory()->sent()->create([
            'contact_id' => $contact->id,
            'total_amount' => 100000,
            'paid_amount' => 30000,
        ]);
        Invoice::factory()->partial()->create([
            'contact_id' => $contact->id,
            'total_amount' => 50000,
            'paid_amount' => 20000,
        ]);

        $outstanding = $this->repository->getOutstandingForContact($contact->id);

        expect($outstanding)->toBe(100000); // (100000-30000) + (50000-20000)
    });

    it('finds invoices in date range', function () {
        Invoice::factory()->create(['invoice_date' => now()->subDays(5)]);
        Invoice::factory()->create(['invoice_date' => now()]);
        Invoice::factory()->create(['invoice_date' => now()->addDays(10)]);

        $range = DateRange::lastDays(7);
        $invoices = $this->repository->findByDateRange($range);

        expect($invoices)->toHaveCount(2);
    });
});
```

---

## Part 5: Value Object Tests

```php
<?php
// File: tests/Unit/Domain/Shared/ValueObjects/MoneyTest.php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Money;

describe('Money Value Object', function () {

    it('creates from minor units', function () {
        $money = new Money(10000, 'IDR');

        expect($money->amount)->toBe(10000);
        expect($money->currency)->toBe('IDR');
    });

    it('creates from major units', function () {
        $money = Money::fromMajor(100.50, 'USD');

        expect($money->amount)->toBe(10050);
    });

    it('adds money', function () {
        $a = new Money(1000, 'IDR');
        $b = new Money(500, 'IDR');

        $result = $a->add($b);

        expect($result->amount)->toBe(1500);
        expect($a->amount)->toBe(1000); // Immutable
    });

    it('subtracts money', function () {
        $a = new Money(1000, 'IDR');
        $b = new Money(300, 'IDR');

        $result = $a->subtract($b);

        expect($result->amount)->toBe(700);
    });

    it('throws on negative result', function () {
        $a = new Money(100, 'IDR');
        $b = new Money(200, 'IDR');

        expect(fn () => $a->subtract($b))->toThrow(InvalidArgumentException::class);
    });

    it('multiplies', function () {
        $money = new Money(1000, 'IDR');

        $result = $money->multiply(2.5);

        expect($result->amount)->toBe(2500);
    });

    it('calculates percentage', function () {
        $money = new Money(10000, 'IDR');

        $result = $money->percentage(11);

        expect($result->amount)->toBe(1100);
    });

    it('formats IDR correctly', function () {
        $money = new Money(1000000, 'IDR');

        expect($money->format())->toBe('Rp 10.000');
    });

    it('formats USD correctly', function () {
        $money = new Money(10050, 'USD');

        expect($money->format())->toBe('$100.50');
    });

    it('throws on different currencies', function () {
        $idr = new Money(1000, 'IDR');
        $usd = new Money(100, 'USD');

        expect(fn () => $idr->add($usd))->toThrow(InvalidArgumentException::class);
    });

    it('compares equality', function () {
        $a = new Money(1000, 'IDR');
        $b = new Money(1000, 'IDR');
        $c = new Money(1000, 'USD');

        expect($a->equals($b))->toBeTrue();
        expect($a->equals($c))->toBeFalse();
    });
});
```

---

## Part 6: Test Configuration

### 6.1 Pest Configuration

```php
<?php
// File: tests/Pest.php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Global test helpers
function createInvoiceWithItems(int $itemCount = 2): \App\Models\Sales\Invoice
{
    return \App\Models\Sales\Invoice::factory()
        ->has(\App\Models\Sales\InvoiceItem::factory()->count($itemCount))
        ->create();
}

function createDraftInvoice(): \App\Models\Sales\Invoice
{
    return \App\Models\Sales\Invoice::factory()->draft()->create();
}

function createContact(): \App\Models\Contacts\Contact
{
    return \App\Models\Contacts\Contact::factory()->create();
}
```

---

## Verification Checklist

- [ ] Test helper traits created
- [ ] Domain service tests comprehensive
- [ ] State machine tests cover all scenarios
- [ ] Repository tests cover all methods
- [ ] Value object tests comprehensive
- [ ] Pest configuration updated
- [ ] All tests pass

---

## Next Phase

Proceed to [Phase 8: API Layer Clean-up](./09-phase-8-api-layer.md).
