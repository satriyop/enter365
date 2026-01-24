<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create authenticated user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Get service from container
    $this->service = app(InvoiceService::class);
});

/*
|--------------------------------------------------------------------------
| InvoiceService Create Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService create operations', function () {
    it('creates invoice with basic data', function () {
        $customer = Contact::factory()->customer()->create();

        $invoice = $this->service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'description' => 'Test invoice',
        ]);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($invoice->contact_id)->toBe($customer->id);
        expect($invoice->status)->toBe(DocumentStatus::Draft);
        expect($invoice->invoice_number)->toStartWith('INV-');
    });

    it('creates invoice with items', function () {
        $customer = Contact::factory()->customer()->create();
        $product = Product::factory()->create();

        $invoice = $this->service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Test item',
                    'quantity' => 2,
                    'unit_price' => 100000,
                ],
            ],
        ]);

        expect($invoice->items)->toHaveCount(1);
        expect($invoice->items->first()->description)->toBe('Test item');
        expect((float) $invoice->items->first()->quantity)->toBe(2.0);
        expect($invoice->items->first()->line_total)->toBe(200000);
    });

    it('auto-generates unique invoice number', function () {
        $customer = Contact::factory()->customer()->create();

        $invoice1 = $this->service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
        ]);

        $invoice2 = $this->service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
        ]);

        expect($invoice1->invoice_number)->not->toBe($invoice2->invoice_number);
    });

    it('sets default values for currency and tax', function () {
        $customer = Contact::factory()->customer()->create();

        $invoice = $this->service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
        ]);

        expect($invoice->currency)->toBe('IDR');
        expect($invoice->exchange_rate)->toBe('1.0000');
    });
});

/*
|--------------------------------------------------------------------------
| InvoiceService Update Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService update operations', function () {
    it('updates draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create();

        $updated = $this->service->update($invoice, [
            'description' => 'Updated description',
            'reference' => 'REF-001',
        ]);

        expect($updated->description)->toBe('Updated description');
        expect($updated->reference)->toBe('REF-001');
    });

    it('throws exception when updating non-draft invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $this->service->update($invoice, ['description' => 'Cannot update']);
    })->throws(DocumentLockedException::class);
});

/*
|--------------------------------------------------------------------------
| InvoiceService Delete Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService delete operations', function () {
    it('deletes draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create();
        $invoiceId = $invoice->id;

        $result = $this->service->delete($invoice);

        expect($result)->toBeTrue();
        expect(Invoice::find($invoiceId))->toBeNull();
    });

    it('throws exception when deleting non-draft invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $this->service->delete($invoice);
    })->throws(DocumentLockedException::class);
});

/*
|--------------------------------------------------------------------------
| InvoiceService Post Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService post operations', function () {
    it('posts draft invoice with items', function () {
        // Mock journal service to avoid actual journal creation
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postInvoice')->once();

        $cogsStrategy = Mockery::mock(COGSRecognitionStrategy::class);
        $cogsStrategy->shouldReceive('onInvoicePost')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);
        $this->app->instance(COGSRecognitionStrategy::class, $cogsStrategy);

        $service = app(InvoiceService::class);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $result = $service->post($invoice);

        expect($result)->toBeInstanceOf(Invoice::class);
        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('throws exception when posting invoice without items', function () {
        $invoice = Invoice::factory()->draft()->create();

        $this->service->post($invoice);
    })->throws(StateTransitionException::class);

    it('cannot post already sent invoice', function () {
        // State machine properly rejects Sent → Sent transition upfront
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $service = app(InvoiceService::class);
        $service->post($invoice);
    })->throws(StateTransitionException::class);

    it('dispatches InvoiceSent event when posting', function () {
        $eventDispatcher = new RecordingEventDispatcher;
        $this->app->instance(\App\Contracts\Events\EventDispatcherInterface::class, $eventDispatcher);

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postInvoice')->once();

        $cogsStrategy = Mockery::mock(COGSRecognitionStrategy::class);
        $cogsStrategy->shouldReceive('onInvoicePost')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);
        $this->app->instance(COGSRecognitionStrategy::class, $cogsStrategy);

        $service = app(InvoiceService::class);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $service->post($invoice);

        expect($eventDispatcher->dispatchCount(InvoiceSent::class))->toBeGreaterThanOrEqual(1);
    });
});

/*
|--------------------------------------------------------------------------
| InvoiceService Void Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService void operations', function () {
    it('voids sent invoice', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->never();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $service = app(InvoiceService::class);
        $result = $service->void($invoice, 'Test cancellation');

        expect($result)->toBeInstanceOf(Invoice::class);
        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cannot void already cancelled invoice', function () {
        // State machine properly rejects Cancelled → Cancelled transition
        $invoice = Invoice::factory()->cancelled()->create(['journal_entry_id' => null]);

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Already cancelled');
    })->throws(StateTransitionException::class);

    it('dispatches InvoiceVoided event when voiding', function () {
        $eventDispatcher = new RecordingEventDispatcher;
        $this->app->instance(\App\Contracts\Events\EventDispatcherInterface::class, $eventDispatcher);

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->never();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $service->void($invoice, 'Test cancellation reason');

        expect($eventDispatcher->dispatchCount(InvoiceVoided::class))->toBeGreaterThanOrEqual(1);
    });
});

/*
|--------------------------------------------------------------------------
| InvoiceService Integration Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService integration', function () {
    it('completes full invoice lifecycle', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('postInvoice')->once();
        $journalService->shouldReceive('reverseEntry')->never();

        $cogsStrategy = Mockery::mock(COGSRecognitionStrategy::class);
        $cogsStrategy->shouldReceive('onInvoicePost')->once();

        $this->app->instance(JournalServiceInterface::class, $journalService);
        $this->app->instance(COGSRecognitionStrategy::class, $cogsStrategy);

        $service = app(InvoiceService::class);
        $customer = Contact::factory()->customer()->create();
        $product = Product::factory()->create();

        // 1. Create invoice
        $invoice = $service->create([
            'contact_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Test product',
                    'quantity' => 1,
                    'unit_price' => 500000,
                ],
            ],
        ]);

        expect($invoice->status)->toBe(DocumentStatus::Draft);
        expect($invoice->items)->toHaveCount(1);

        // 2. Update while draft
        $invoice = $service->update($invoice, [
            'description' => 'Updated for testing',
        ]);
        expect($invoice->description)->toBe('Updated for testing');

        // 3. Post invoice
        $result = $service->post($invoice);
        expect($result)->toBeInstanceOf(Invoice::class);
        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);

        // 4. Void invoice
        $result = $service->void($invoice->fresh(), 'Customer requested cancellation');
        expect($result)->toBeInstanceOf(Invoice::class);
        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });
});
