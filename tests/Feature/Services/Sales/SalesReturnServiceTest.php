<?php

declare(strict_types=1);

use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);

    // Seed chart of accounts for journal entry tests
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    $this->service = app(SalesReturnServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates sales return with items', function () {
        $invoice = Invoice::factory()->create();

        $data = [
            'invoice_id' => $invoice->id,
            'contact_id' => $this->contact->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Damaged product',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Returned Item',
                    'quantity' => 5,
                    'unit_price' => 50000,
                ],
            ],
        ];

        $return = $this->service->create($data);

        expect($return)
            ->toBeInstanceOf(SalesReturn::class)
            ->status->toBe(DocumentStatus::Draft)
            ->invoice_id->toBe($invoice->id)
            ->reason->toBe('Damaged product')
            ->items->toHaveCount(1);
    });

    test('creates sales return from invoice', function () {
        $invoice = Invoice::factory()
            ->for($this->contact)
            ->create(['tax_rate' => 11.00]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'description' => 'Invoice Item',
            'quantity' => 10,
            'unit_price' => 100000,
            'line_total' => 1000000,
        ]);

        $return = $this->service->createFromInvoice($invoice, [
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'Customer not satisfied',
        ]);

        expect($return)
            ->invoice_id->toBe($invoice->id)
            ->contact_id->toBe($invoice->contact_id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->reason->toBe('Customer not satisfied')
            ->items->toHaveCount(1);

        expect((float) $return->tax_rate)->toBe(11.00);
    });

    test('updates draft sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['reason' => 'Old Reason']);

        $updated = $this->service->update($return, [
            'reason' => 'New Reason',
            'notes' => 'Additional notes',
        ]);

        expect($updated)
            ->reason->toBe('New Reason')
            ->notes->toBe('Additional notes');
    });

    test('throws exception when updating non-draft sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $this->service->update($return, ['reason' => 'Test']);
    })->throws(DocumentLockedException::class);

    test('deletes draft sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        $result = $this->service->delete($return);

        expect($result)->toBeTrue()
            ->and(SalesReturn::find($return->id))->toBeNull();
    });

    test('throws exception when deleting non-draft sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $this->service->delete($return);
    })->throws(DocumentLockedException::class);
});

describe('Workflow Transitions', function () {
    test('submits draft sales return with items', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create();

        $submitted = $this->service->submit($return, $this->user->id);

        expect($submitted)
            ->status->toBe(DocumentStatus::Submitted)
            ->submitted_at->not->toBeNull();
    });

    test('throws exception when submitting without items', function () {
        $return = SalesReturn::factory()->create();

        $this->service->submit($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('approves submitted sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory()->state([
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

    test('throws exception when approving non-submitted sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->approve($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('rejects submitted sales return with reason', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Submitted]);

        $rejected = $this->service->reject($return, 'Return not valid', $this->user->id);

        expect($rejected)
            ->status->toBe(DocumentStatus::Rejected)
            ->rejection_reason->toBe('Return not valid');
    });

    test('completes approved sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $completed = $this->service->complete($return, $this->user->id);

        expect($completed)
            ->status->toBe(DocumentStatus::Completed)
            ->completed_at->not->toBeNull();
    });

    test('throws exception when completing non-approved sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->complete($return, $this->user->id);
    })->throws(StateTransitionException::class);

    test('cancels sales return in allowed states', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $cancelled = $this->service->cancel($return, 'Customer changed mind');

        expect($cancelled)->status->toBe(DocumentStatus::Cancelled);
    });

    test('throws exception when canceling approved sales return', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Approved]);

        $this->service->cancel($return, 'Test');
    })->throws(StateTransitionException::class);
});

describe('Approval Pipeline', function () {
    test('approves sales return successfully', function () {
        $return = SalesReturn::factory()
            ->has(SalesReturnItem::factory()->state([
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
    test('gets sales returns for invoice', function () {
        $invoice = Invoice::factory()->create();

        SalesReturn::factory()->count(3)->create(['invoice_id' => $invoice->id]);
        SalesReturn::factory()->create(); // Different invoice

        $results = $this->service->getForInvoice($invoice);

        expect($results)->toHaveCount(3);
    });
});

describe('Statistics', function () {
    test('returns sales return statistics', function () {
        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->count(2)
            ->create([
                'status' => DocumentStatus::Draft,
                'total_amount' => 100000,
            ]);

        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->count(3)
            ->create([
                'status' => DocumentStatus::Approved,
                'total_amount' => 200000,
            ]);

        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
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
        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create([
                'return_date' => '2024-01-15',
                'total_amount' => 100000,
            ]);

        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create([
                'return_date' => '2024-02-15',
                'total_amount' => 200000,
            ]);

        $stats = $this->service->getStatistics('2024-01-01', '2024-01-31');

        expect($stats['total_count'])->toBe(1);
    });

    test('groups statistics by reason', function () {
        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->count(2)
            ->create([
                'reason' => 'Damaged',
                'total_amount' => 100000,
            ]);

        SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->create([
                'reason' => 'Wrong Item',
                'total_amount' => 200000,
            ]);

        $stats = $this->service->getStatistics();

        expect($stats['by_reason'])
            ->toHaveKey('Damaged')
            ->toHaveKey('Wrong Item');

        expect($stats['by_reason']['Damaged'])
            ->count->toBe(2)
            ->total->toBe(200000);
    });
});
