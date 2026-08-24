<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Core\AuditLog;
use App\Models\Inventory\Product;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    it('audits only the delivery orders this void cancelled', function () {
        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->never();
        $this->app->instance(JournalServiceInterface::class, $journalService);

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        DeliveryOrder::factory()->draft()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
        ]);
        DeliveryOrder::factory()->cancelled()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
        ]);

        app(InvoiceService::class)->void($invoice, 'Batal uji');

        $log = AuditLog::query()
            ->where('action', AuditLog::ACTION_VOIDED)
            ->where('auditable_id', $invoice->id)
            ->latest('id')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->new_values['cancelled_delivery_orders'])->toBe(1)
            ->and($log->new_values['cancelled_sales_returns'])->toBe(0);
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

/*
|--------------------------------------------------------------------------
| InvoiceService Void Cascade Tests
|--------------------------------------------------------------------------
*/

describe('InvoiceService void cascade', function () {
    it('reverses COGS JE when voiding posted invoice', function () {
        // Create a COGS JE linked to the invoice (simulating on_invoice strategy)
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $cogsJe = JournalEntry::factory()->create([
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'is_reversed' => false,
            'is_posted' => true,
        ]);

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')
            ->once()
            ->withArgs(fn ($je) => $je->id === $cogsJe->id);

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Test COGS reversal');

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cancels approved sales returns when voiding invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $sr = SalesReturn::factory()
            ->forInvoice($invoice)
            ->approved()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        // Mock SalesReturnService to verify cancel() is called
        $salesReturnService = Mockery::mock(SalesReturnServiceInterface::class);
        $salesReturnService->shouldReceive('cancel')
            ->once()
            ->withArgs(function ($salesReturn, $reason) use ($sr) {
                return $salesReturn->id === $sr->id
                    && str_contains($reason, 'Faktur dibatalkan');
            })
            ->andReturnUsing(function ($salesReturn, $reason) {
                $salesReturn->status = DocumentStatus::Cancelled;
                $salesReturn->save();

                return $salesReturn;
            });

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->zeroOrMoreTimes();

        $this->app->instance(SalesReturnServiceInterface::class, $salesReturnService);
        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Test SR cascade');

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('reverses shipped DO when voiding invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $do = DeliveryOrder::factory()
            ->forInvoice($invoice)
            ->shipped()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create();

        // Mock DO service to verify reverseShipment() is called
        $doService = Mockery::mock(DeliveryOrderServiceInterface::class);
        $doService->shouldReceive('reverseShipment')
            ->once()
            ->withArgs(function ($deliveryOrder, $reason) use ($do) {
                return $deliveryOrder->id === $do->id
                    && str_contains($reason, 'Faktur dibatalkan');
            })
            ->andReturnUsing(function ($deliveryOrder, $reason) {
                $deliveryOrder->status = DocumentStatus::Cancelled;
                $deliveryOrder->save();

                return $deliveryOrder;
            });

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->zeroOrMoreTimes();

        $this->app->instance(DeliveryOrderServiceInterface::class, $doService);
        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Test shipped DO cascade');

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('blocks void when delivered DO exists', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        DeliveryOrder::factory()
            ->forInvoice($invoice)
            ->delivered()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create();

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Should be blocked');
    })->throws(BusinessRuleException::class, 'sudah diterima');

    it('blocks void when completed SR exists', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        SalesReturn::factory()
            ->forInvoice($invoice)
            ->completed()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        $service = app(InvoiceService::class);
        $service->void($invoice, 'Should be blocked');
    })->throws(BusinessRuleException::class, 'sudah selesai diproses');

    it('handles mixed cascade: draft DO + shipped DO + approved SR', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        // Draft DO — should be cancelled directly
        $draftDo = DeliveryOrder::factory()
            ->forInvoice($invoice)
            ->draft()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create();

        // Shipped DO — should be reversed via service
        $shippedDo = DeliveryOrder::factory()
            ->forInvoice($invoice)
            ->shipped()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create();

        // Approved SR — should be cancelled via service
        $approvedSr = SalesReturn::factory()
            ->forInvoice($invoice)
            ->approved()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        // Draft SR — should be cancelled directly
        $draftSr = SalesReturn::factory()
            ->forInvoice($invoice)
            ->draft()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        $doService = Mockery::mock(DeliveryOrderServiceInterface::class);
        $doService->shouldReceive('reverseShipment')
            ->once()
            ->andReturnUsing(function ($do) {
                $do->status = DocumentStatus::Cancelled;
                $do->save();

                return $do;
            });

        $salesReturnService = Mockery::mock(SalesReturnServiceInterface::class);
        $salesReturnService->shouldReceive('cancel')
            ->once()
            ->andReturnUsing(function ($sr) {
                $sr->status = DocumentStatus::Cancelled;
                $sr->save();

                return $sr;
            });

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->zeroOrMoreTimes();

        $this->app->instance(DeliveryOrderServiceInterface::class, $doService);
        $this->app->instance(SalesReturnServiceInterface::class, $salesReturnService);
        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);
        $result = $service->void($invoice, 'Full cascade test');

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($draftDo->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($shippedDo->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($approvedSr->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($draftSr->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('still allows void when only draft/confirmed DOs and draft/submitted SRs exist', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['journal_entry_id' => null]);

        $confirmedDo = DeliveryOrder::factory()
            ->forInvoice($invoice)
            ->confirmed()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->create();

        $submittedSr = SalesReturn::factory()
            ->forInvoice($invoice)
            ->submitted()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        $journalService = Mockery::mock(JournalServiceInterface::class);
        $journalService->shouldReceive('reverseEntry')->zeroOrMoreTimes();

        $this->app->instance(JournalServiceInterface::class, $journalService);

        $service = app(InvoiceService::class);
        $result = $service->void($invoice, 'Cascade cancellable only');

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($confirmedDo->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($submittedSr->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });
});
