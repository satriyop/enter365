<?php

declare(strict_types=1);

use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vendor = Contact::factory()->vendor()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    $this->service = app(PurchaseOrderServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates purchase order with items', function () {
        $data = [
            'contact_id' => $this->vendor->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'subject' => 'Test PO',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $po = $this->service->create($data);

        expect($po)
            ->toBeInstanceOf(PurchaseOrder::class)
            ->status->toBe(DocumentStatus::Draft)
            ->contact_id->toBe($this->vendor->id)
            ->po_number->not->toBeEmpty()
            ->items->toHaveCount(1);

        expect($po->items->first())
            ->product_id->toBe($this->product->id);

        expect((float) $po->items->first()->quantity_received)->toBe(0.0);
    });

    test('calculates item totals correctly', function () {
        $po = $this->service->create([
            'contact_id' => $this->vendor->id,
            'po_date' => now()->toDateString(),
            'tax_rate' => 11.00,
            'items' => [
                [
                    'description' => 'Item 1',
                    'quantity' => 10,
                    'unit_price' => 100000,
                    'discount_percent' => 10,
                ],
            ],
        ]);

        $item = $po->items->first();

        expect($item)
            ->discount_amount->toBe(100000) // (10 * 100000) * 0.1
            ->line_total->toBe(900000)      // 1000000 - 100000
            ->tax_amount->toBe(99000);      // 900000 * 0.11
    });

    test('applies default values', function () {
        $po = $this->service->create([
            'contact_id' => $this->vendor->id,
            'po_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        expect($po)
            ->currency->toBe('IDR')
            ->revision->toBe(0);

        expect((float) $po->exchange_rate)->toBe(1.0);
        expect((float) $po->tax_rate)->toBe(11.00);
    });

    test('updates draft purchase order', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['subject' => 'Old Subject']);

        $updated = $this->service->update($po, ['subject' => 'New Subject']);

        expect($updated->subject)->toBe('New Subject');
    });

    test('throws exception when updating non-draft PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $this->service->update($po, ['subject' => 'Test']);
    })->throws(DocumentLockedException::class, 'draft');
});

describe('Workflow Transitions', function () {
    test('submits draft purchase order', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create();

        $submitted = $this->service->submit($po, $this->user->id);

        expect($submitted)
            ->status->toBe(DocumentStatus::Submitted)
            ->submitted_at->not->toBeNull();
    });

    test('throws exception when submitting PO without items', function () {
        $po = PurchaseOrder::factory()->create();

        $this->service->submit($po, $this->user->id);
    })->throws(StateTransitionException::class);

    test('approves submitted purchase order', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $approved = $this->service->approve($po, $this->user->id);

        expect($approved)
            ->status->toBe(DocumentStatus::Approved)
            ->approved_at->not->toBeNull();
    });

    test('throws exception when approving non-submitted PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->approve($po, $this->user->id);
    })->throws(StateTransitionException::class);

    test('rejects submitted purchase order with reason', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $rejected = $this->service->reject($po, 'Harga tidak sesuai budget', $this->user->id);

        expect($rejected)
            ->status->toBe(DocumentStatus::Rejected)
            ->rejection_reason->toBe('Harga tidak sesuai budget');
    });

    test('throws exception when rejecting without reason', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]);

        $this->service->reject($po, '', $this->user->id);
    })->throws(BusinessRuleException::class, 'alasan');

    test('cancels purchase order with reason', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $cancelled = $this->service->cancel($po, 'Vendor tidak tersedia', $this->user->id);

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled)
            ->cancellation_reason->toBe('Vendor tidak tersedia');
    });

    test('throws exception when canceling without reason', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create();

        $this->service->cancel($po, '', $this->user->id);
    })->throws(BusinessRuleException::class, 'alasan');
});

// Note: Receiving is handled by GoodsReceiptNoteService, not PurchaseOrderService.
// See tests/Feature/Services/Purchasing/GoodsReceiptNoteServiceTest.php for receiving tests.

describe('Duplication', function () {
    test('duplicates purchase order as new draft', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->count(3), 'items')
            ->create([
                'status' => DocumentStatus::Approved,
                'revision' => 2,
            ]);

        $duplicate = $this->service->duplicate($po);

        expect($duplicate)
            ->status->toBe(DocumentStatus::Draft)
            ->po_number->not->toBe($po->po_number)
            ->revision->toBe(0)
            ->items->toHaveCount(3);

        // Items should have zero received
        $duplicate->items->each(fn ($item) => expect((float) $item->quantity_received)->toBe(0.0));
    });

    test('preserves financial data when duplicating', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'currency' => 'USD',
                'exchange_rate' => 15000,
                'tax_rate' => 11.00,
            ]);

        $duplicate = $this->service->duplicate($po);

        expect($duplicate)->currency->toBe('USD');

        expect((float) $duplicate->exchange_rate)->toBe(15000.0);
        expect((float) $duplicate->tax_rate)->toBe(11.00);
    });
});

describe('Bill Conversion', function () {
    test('converts fully received PO to bill', function () {
        // Bill number will be auto-generated, no seeder needed

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state([
                'quantity' => 10,
                'quantity_received' => 10,
            ])->count(2), 'items')
            ->create([
                'status' => DocumentStatus::Received,
                'total_amount' => 1000000,
                'converted_to_bill_id' => null,
            ]);

        $bill = $this->service->convertToBill($po);

        expect($bill)
            ->contact_id->toBe($po->contact_id)
            ->items->toHaveCount(2);

        // Check PO was marked as converted
        $po->refresh();
        expect($po->converted_to_bill_id)->toBe($bill->id);
    });
});

describe('Statistics', function () {
    test('returns outstanding purchase orders', function () {
        // Create some approved POs with items
        PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->count(3)
            ->create(['status' => DocumentStatus::Approved]);

        $outstanding = $this->service->getOutstanding();

        expect($outstanding)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    });

    test('filters outstanding by contact', function () {
        $vendor1 = Contact::factory()->vendor()->create();
        $vendor2 = Contact::factory()->vendor()->create();

        PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'contact_id' => $vendor1->id,
                'status' => DocumentStatus::Approved,
            ]);

        PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->create([
                'contact_id' => $vendor2->id,
                'status' => DocumentStatus::Approved,
            ]);

        $outstanding = $this->service->getOutstanding($vendor1->id);

        expect($outstanding->count())->toBe(1)
            ->and($outstanding->first()->contact_id)->toBe($vendor1->id);
    });

    test('returns purchase order statistics', function () {
        PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->count(2)
            ->create(['status' => DocumentStatus::Approved]);

        $stats = $this->service->getStatistics();

        expect($stats)->toBeArray();
    });
});
