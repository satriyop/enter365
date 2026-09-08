<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
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

describe('parity feature set (quotations)', function () {
    it('exposes quotations on the parity preset', function () {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'parity')
            ->assertJsonPath('data.modules.quotations', true)
            ->assertJsonPath('data.modules.invoices', true)
            ->assertJsonPath('data.modules.purchase_orders', true);

        $this->getJson('/api/v1/quotations')->assertOk();
        $this->getJson('/api/v1/purchase-orders')->assertOk();
    });

    it('lets the owner create, submit, approve, and convert a quotation to an invoice', function () {
        Sanctum::actingAs($this->owner);

        $customer = Contact::query()->whereIn('type', ['customer', 'both'])->first()
            ?? Contact::factory()->customer()->create();

        $create = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'subject' => 'Parity catering quotation',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Kopi catering',
                    'quantity' => 1,
                    'unit' => 'paket',
                    'unit_price' => 200_000,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status.value', 'draft');

        $quotationId = (int) $create->json('data.id');

        $this->postJson("/api/v1/quotations/{$quotationId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'submitted');

        $this->postJson("/api/v1/quotations/{$quotationId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        $convert = $this->postJson("/api/v1/quotations/{$quotationId}/convert-to-invoice");
        $convert->assertCreated();

        $quotation = Quotation::query()->findOrFail($quotationId);
        expect($quotation->converted_to_invoice_id)->not->toBeNull()
            ->and(Invoice::query()->whereKey($quotation->converted_to_invoice_id)->exists())->toBeTrue();
    });

    it('still 404s quotations on the kasir-only pos preset', function () {
        applyFeaturePreset('pos');
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/quotations')->assertNotFound();
    });

    it('turns quotations on under pos when FEATURE_QUOTATIONS is overridden', function () {
        applyFeaturePreset('pos');
        config(['features.modules.quotations' => true]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'pos')
            ->assertJsonPath('data.modules.quotations', true);

        $this->getJson('/api/v1/quotations')->assertOk();
    });
});
