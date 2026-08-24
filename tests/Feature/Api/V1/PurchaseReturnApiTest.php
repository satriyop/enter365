<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = authenticatedAdmin();
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
});

describe('Purchase Return CRUD', function () {

    it('can list all purchase returns', function () {
        PurchaseReturn::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/purchase-returns');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/purchase-returns');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'return_number',
                        'status' => ['value', 'label', 'color'],
                        'return_date',
                        'total_amount',
                    ],
                ],
            ]);
    });

    it('can filter purchase returns by status', function () {
        PurchaseReturn::factory()->draft()->count(3)->create();
        PurchaseReturn::factory()->submitted()->count(2)->create();

        $response = $this->getJson('/api/v1/purchase-returns?status=draft');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter purchase returns by contact', function () {
        $contact = Contact::factory()->vendor()->create();
        PurchaseReturn::factory()->forContact($contact)->count(2)->create();
        PurchaseReturn::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/purchase-returns?contact_id={$contact->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter purchase returns by date range', function () {
        PurchaseReturn::factory()->create(['return_date' => '2025-12-01']);
        PurchaseReturn::factory()->create(['return_date' => '2025-12-15']);
        PurchaseReturn::factory()->create(['return_date' => '2025-12-30']);

        $response = $this->getJson('/api/v1/purchase-returns?start_date=2025-12-10&end_date=2025-12-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a purchase return with items', function () {
        $contact = Contact::factory()->vendor()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/purchase-returns', [
            'contact_id' => $contact->id,
            'return_date' => now()->toDateString(),
            'reason' => 'damaged',
            'tax_rate' => 11,
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Cable NYY 3x2.5mm (Rusak)',
                    'quantity' => 100,
                    'unit' => 'meter',
                    'unit_price' => 15000,
                    'condition' => 'damaged',
                ],
                [
                    'description' => 'MCB 20A (Kelebihan Order)',
                    'quantity' => 10,
                    'unit' => 'pcs',
                    'unit_price' => 85000,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.items');

        expect($response->json('data.return_number'))->toStartWith('PR-');
        expect($response->json('data.subtotal'))->toBe(2350000); // 100*15k + 10*85k
    });

    it('validates required fields when creating purchase return', function () {
        $response = $this->postJson('/api/v1/purchase-returns', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'return_date', 'items']);
    });

    it('can show a single purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->create();
        PurchaseReturnItem::factory()->forPurchaseReturn($purchaseReturn)->count(2)->create();

        $response = $this->getJson("/api/v1/purchase-returns/{$purchaseReturn->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $purchaseReturn->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();
        PurchaseReturnItem::factory()->forPurchaseReturn($purchaseReturn)->create();

        $response = $this->putJson("/api/v1/purchase-returns/{$purchaseReturn->id}", [
            'reason' => 'excess_quantity',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reason', 'excess_quantity')
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonCount(1, 'data.items');
    });

    it('cannot update a non-draft purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->create();

        $response = $this->putJson("/api/v1/purchase-returns/{$purchaseReturn->id}", [
            'reason' => 'quality_issue',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/purchase-returns/{$purchaseReturn->id}");

        $response->assertOk();
        $this->assertSoftDeleted('purchase_returns', ['id' => $purchaseReturn->id]);
    });

    it('cannot delete a non-draft purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->create();

        $response = $this->deleteJson("/api/v1/purchase-returns/{$purchaseReturn->id}");

        $response->assertUnprocessable();
    });
});

describe('Purchase Return Workflow', function () {

    it('can submit a draft purchase return with items', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();
        PurchaseReturnItem::factory()->forPurchaseReturn($purchaseReturn)->count(2)->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'submitted');

        $purchaseReturn->refresh();
        expect($purchaseReturn->submitted_at)->not->toBeNull();
        expect($purchaseReturn->submitted_by)->toBe($this->user->id);
    });

    it('cannot submit a purchase return without items', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/submit");

        $response->assertUnprocessable();
    });

    it('can approve a submitted purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->withTotals(100000)->create();
        PurchaseReturnItem::factory()->forPurchaseReturn($purchaseReturn)->withLineTotal(100000)->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        $purchaseReturn->refresh();
        expect($purchaseReturn->approved_at)->not->toBeNull();
        expect($purchaseReturn->approved_by)->toBe($this->user->id);
        expect($purchaseReturn->journal_entry_id)->not->toBeNull();
    });

    it('cannot approve a non-submitted purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/approve");

        $response->assertUnprocessable();
    });

    it('can reject a submitted purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/reject", [
            'reason' => 'Supplier does not accept return',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Supplier does not accept return');

        $purchaseReturn->refresh();
        expect($purchaseReturn->rejected_at)->not->toBeNull();
        expect($purchaseReturn->rejected_by)->toBe($this->user->id);
    });

    it('cannot reject a non-submitted purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/reject");

        $response->assertUnprocessable();
    });

    it('can complete an approved purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        $purchaseReturn->refresh();
        expect($purchaseReturn->completed_at)->not->toBeNull();
        expect($purchaseReturn->completed_by)->toBe($this->user->id);
    });

    it('cannot complete a non-approved purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/complete");

        $response->assertUnprocessable();
    });

    it('can cancel a draft purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/cancel", [
            'reason' => 'No longer needed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('can cancel a submitted purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/cancel", [
            'reason' => 'Supplier agreed to credit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel an approved purchase return', function () {
        $purchaseReturn = PurchaseReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/purchase-returns/{$purchaseReturn->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Purchase Return from Bill', function () {

    it('can create purchase return from bill', function () {
        $bill = Bill::factory()->create();
        BillItem::factory()->for($bill)->count(2)->create();

        $response = $this->postJson("/api/v1/bills/{$bill->id}/create-purchase-return", [
            'return_date' => now()->toDateString(),
            'reason' => 'wrong_item',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bill_id', $bill->id)
            ->assertJsonPath('data.contact_id', $bill->contact_id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can list purchase returns for a bill', function () {
        $bill = Bill::factory()->create();
        PurchaseReturn::factory()->forBill($bill)->count(2)->create();
        PurchaseReturn::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/bills/{$bill->id}/purchase-returns");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('Purchase Return with Warehouse', function () {

    it('can create purchase return with warehouse for inventory', function () {
        $contact = Contact::factory()->vendor()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson('/api/v1/purchase-returns', [
            'contact_id' => $contact->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'excess_quantity',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse_id', $warehouse->id);
    });
});

describe('Purchase Return Statistics', function () {

    it('returns purchase return statistics', function () {
        PurchaseReturn::factory()->draft()->count(2)->create();
        PurchaseReturn::factory()->submitted()->count(3)->create();
        PurchaseReturn::factory()->approved()->withTotals(500000)->count(1)->create();
        PurchaseReturn::factory()->completed()->withTotals(300000)->count(1)->create();
        PurchaseReturn::factory()->cancelled()->count(1)->create();

        $response = $this->getJson('/api/v1/purchase-returns-statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_count',
                'draft_count',
                'submitted_count',
                'approved_count',
                'completed_count',
                'cancelled_count',
                'total_value',
                'by_reason',
            ]);

        expect($response->json('total_count'))->toBe(8);
        expect($response->json('draft_count'))->toBe(2);
        expect($response->json('submitted_count'))->toBe(3);
    });

    it('can filter statistics by date range', function () {
        PurchaseReturn::factory()->create(['return_date' => '2025-12-01']);
        PurchaseReturn::factory()->create(['return_date' => '2025-12-15']);
        PurchaseReturn::factory()->create(['return_date' => '2025-12-30']);

        $response = $this->getJson('/api/v1/purchase-returns-statistics?start_date=2025-12-10&end_date=2025-12-20');

        $response->assertOk();
        expect($response->json('total_count'))->toBe(1);
    });
});
