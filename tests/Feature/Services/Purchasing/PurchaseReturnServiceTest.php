<?php

declare(strict_types=1);

use App\Contracts\Purchasing\PurchaseReturnServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vendor = Contact::factory()->vendor()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    // Seed chart of accounts for journal entry tests
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    $this->service = app(PurchaseReturnServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates purchase return with items', function () {
        $bill = Bill::factory()->create();

        $data = [
            'bill_id' => $bill->id,
            'contact_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Quality issue',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Defective Item',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $return = $this->service->create($data);

        expect($return)
            ->toBeInstanceOf(PurchaseReturn::class)
            ->status->toBe(DocumentStatus::Draft)
            ->bill_id->toBe($bill->id)
            ->reason->toBe('Quality issue')
            ->items->toHaveCount(1);
    });

    test('creates purchase return from bill', function () {
        $bill = Bill::factory()
            ->for($this->vendor, 'contact')
            ->create(['tax_rate' => 11.00]);

        $bill->items()->create([
            'product_id' => $this->product->id,
            'description' => 'Bill Item',
            'quantity' => 20,
            'unit_price' => 80000,
            'line_total' => 1600000,
        ]);

        $return = $this->service->createFromBill($bill, [
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'Not as specified',
        ]);

        expect($return)
            ->bill_id->toBe($bill->id)
            ->contact_id->toBe($bill->contact_id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->reason->toBe('Not as specified')
            ->items->toHaveCount(1);

        expect((float) $return->tax_rate)->toBe(11.00);
    });

    test('updates draft purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['reason' => 'Old Reason']);

        $updated = $this->service->update($return, [
            'reason' => 'Updated Reason',
            'notes' => 'New notes',
        ]);

        expect($updated)
            ->reason->toBe('Updated Reason')
            ->notes->toBe('New notes');
    });

    test('throws exception when updating non-draft purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $this->service->update($return, ['reason' => 'Test']);
    })->throws(DocumentLockedException::class);

    test('deletes draft purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create();

        $result = $this->service->delete($return);

        expect($result)->toBeTrue()
            ->and(PurchaseReturn::find($return->id))->toBeNull();
    });

    test('throws exception when deleting non-draft purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $this->service->delete($return);
    })->throws(DocumentLockedException::class);
});

describe('Workflow Transitions', function () {
    test('submits draft purchase return with items', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create();

        $submitted = $this->service->submit($return, $this->user->id);

        expect($submitted)
            ->status->toBe(DocumentStatus::Submitted)
            ->submitted_at->not->toBeNull();
    });

    test('throws exception when submitting without items', function () {
        $return = PurchaseReturn::factory()->create();

        $this->service->submit($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('approves submitted purchase return', function () {
        // Seed stock so stock-out doesn't fail
        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 100000,
            'total_value' => 10000000,
        ]);

        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory()->state([
                'product_id' => $this->product->id,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $approved = $this->service->approve($return, $this->user->id);

        expect($approved)
            ->status->toBe(DocumentStatus::Approved)
            ->approved_at->not->toBeNull();
    });

    test('throws exception when approving non-submitted purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->approve($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('rejects submitted purchase return with reason', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $rejected = $this->service->reject($return, 'Insufficient evidence', $this->user->id);

        expect($rejected)
            ->status->toBe(DocumentStatus::Rejected)
            ->rejection_reason->toBe('Insufficient evidence');
    });

    test('completes approved purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $completed = $this->service->complete($return, $this->user->id);

        expect($completed)
            ->status->toBe(DocumentStatus::Completed)
            ->completed_at->not->toBeNull();
    });

    test('throws exception when completing non-approved purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->complete($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('cancels purchase return in allowed states', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $cancelled = $this->service->cancel($return, 'Vendor refused', $this->user->id);

        expect($cancelled)->status->toBe(DocumentStatus::Cancelled);
    });

    test('throws exception when canceling completed purchase return', function () {
        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Completed]);

        $this->service->cancel($return, 'Test', $this->user->id);
    })->throws(StateTransitionException::class);
});

describe('Approval Pipeline', function () {
    test('approves purchase return successfully', function () {
        // Seed stock so stock-out doesn't fail
        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 100000,
            'total_value' => 10000000,
        ]);

        $return = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory()->state([
                'product_id' => $this->product->id,
                'quantity' => 10,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::Submitted,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $approved = $this->service->approve($return, $this->user->id);

        expect($approved->status)->toBe(DocumentStatus::Approved);
    });
});

describe('Query Methods', function () {
    test('gets purchase returns for bill', function () {
        $bill = Bill::factory()->create();

        PurchaseReturn::factory()->count(3)->create(['bill_id' => $bill->id]);
        PurchaseReturn::factory()->create(); // Different bill

        $results = $this->service->getForBill($bill);

        expect($results)->toHaveCount(3);
    });
});

describe('Statistics', function () {
    test('returns purchase return statistics', function () {
        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->count(2)
            ->create([
                'status' => DocumentStatus::Draft,
                'total_amount' => 100000,
            ]);

        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->count(3)
            ->create([
                'status' => DocumentStatus::Approved,
                'total_amount' => 200000,
            ]);

        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Cancelled,
                'total_amount' => 50000,
            ]);

        $stats = $this->service->getStatistics();

        expect($stats)
            ->toBeArray()
            ->total_count->toBe(6)
            ->draft_count->toBe(2)
            ->approved_count->toBe(3)
            ->cancelled_count->toBe(1);

        // Cancelled should not be included in total value
        expect($stats['total_value'])->toBe(800000); // (2*100000) + (3*200000)
    });

    test('filters statistics by date range', function () {
        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create([
                'return_date' => '2024-01-15',
                'total_amount' => 100000,
            ]);

        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create([
                'return_date' => '2024-02-15',
                'total_amount' => 200000,
            ]);

        $stats = $this->service->getStatistics('2024-01-01', '2024-01-31');

        expect($stats['total_count'])->toBe(1);
    });

    test('groups statistics by reason', function () {
        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->count(2)
            ->create([
                'reason' => 'Defective',
                'total_amount' => 100000,
            ]);

        PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->create([
                'reason' => 'Wrong Specification',
                'total_amount' => 200000,
            ]);

        $stats = $this->service->getStatistics();

        expect($stats['by_reason'])
            ->toHaveKey('Defective')
            ->toHaveKey('Wrong Specification');

        expect($stats['by_reason']['Defective'])
            ->count->toBe(2)
            ->total->toBe(200000);
    });
});
