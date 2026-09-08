<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    applyFeaturePreset('parity');
    seedDemoFoundation($this);
    seedDemoProfile($this, DemoSeeder::DEMO_POS);

    $this->owner = User::query()->where('email', 'admin@example.com')->firstOrFail();
});

describe('parity feature set (purchase orders)', function () {
    it('exposes purchase orders on the parity preset', function () {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'parity')
            ->assertJsonPath('data.modules.purchase_orders', true)
            ->assertJsonPath('data.modules.goods_receipt_notes', false);

        $this->getJson('/api/v1/purchase-orders')->assertOk();
        $this->getJson('/api/v1/goods-receipt-notes')->assertNotFound();
    });

    it('lets the owner create, submit, and approve a purchase order', function () {
        Sanctum::actingAs($this->owner);

        $vendor = Contact::query()->whereIn('type', ['vendor', 'supplier', 'both'])->first()
            ?? Contact::factory()->vendor()->create();

        $create = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'subject' => 'Parity kopi beans',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Green beans',
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unit_price' => 50_000,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status.value', 'draft');

        $poId = (int) $create->json('data.id');

        $this->postJson("/api/v1/purchase-orders/{$poId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'submitted');

        $this->postJson("/api/v1/purchase-orders/{$poId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        expect(PurchaseOrder::query()->whereKey($poId)->exists())->toBeTrue();
    });

    it('still 404s purchase orders on the kasir-only pos preset', function () {
        applyFeaturePreset('pos');
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/purchase-orders')->assertNotFound();
    });

    it('turns purchase orders on under pos when FEATURE_PURCHASE_ORDERS is overridden', function () {
        applyFeaturePreset('pos');
        config(['features.modules.purchase_orders' => true]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/purchase-orders')->assertOk();
    });
});
