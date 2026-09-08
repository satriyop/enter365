<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
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
    $this->akuntan = User::query()->where('email', 'rina@kopitiam57.test')->firstOrFail();
});

describe('parity feature set (POS till + customer invoices)', function () {
    it('exposes invoices and payments while keeping quotations and POs off', function () {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'parity')
            ->assertJsonPath('data.modules.pos', true)
            ->assertJsonPath('data.modules.invoices', true)
            ->assertJsonPath('data.modules.payments', true)
            ->assertJsonPath('data.modules.quotations', true)
            ->assertJsonPath('data.modules.purchase_orders', false);

        $this->getJson('/api/v1/invoices')->assertOk();
        $this->getJson('/api/v1/payments')->assertOk();
        $this->getJson('/api/v1/quotations')->assertOk();
        $this->getJson('/api/v1/purchase-orders')->assertNotFound();
    });

    it('lets accountant Rina list, create, post, and pay a customer invoice', function () {
        Sanctum::actingAs($this->akuntan);

        $this->getJson('/api/v1/invoices')->assertOk();

        $customer = Contact::query()->whereIn('type', ['customer', 'both'])->first()
            ?? Contact::factory()->customer()->create();
        $bankAccount = Account::query()->where('code', '1-1010')->firstOrFail();

        $create = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'description' => 'Parity customer invoice',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Kopi catering',
                    'quantity' => 2,
                    'unit' => 'paket',
                    'unit_price' => 100_000,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status.value', 'draft');

        $invoiceId = (int) $create->json('data.id');
        expect(Invoice::query()->whereKey($invoiceId)->exists())->toBeTrue();

        $this->postJson("/api/v1/invoices/{$invoiceId}/post")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'sent');

        $pay = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => (int) $create->json('data.total_amount'),
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
            'invoice_id' => $invoiceId,
        ]);

        $pay->assertCreated()
            ->assertJsonPath('data.payable_id', $invoiceId);

        expect(Invoice::query()->findOrFail($invoiceId)->paid_amount)
            ->toBe((int) $create->json('data.total_amount'));
    });

    it('still 404s invoices on the kasir-only pos preset', function () {
        applyFeaturePreset('pos');
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'pos')
            ->assertJsonPath('data.modules.invoices', false);

        $this->getJson('/api/v1/invoices')->assertNotFound();
    });

    it('turns invoices on under pos when FEATURE_INVOICES is overridden', function () {
        applyFeaturePreset('pos');
        config([
            'features.modules.invoices' => true,
            'features.modules.payments' => true,
        ]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'pos')
            ->assertJsonPath('data.modules.invoices', true)
            ->assertJsonPath('data.modules.quotations', false);

        $this->getJson('/api/v1/invoices')->assertOk();
        $this->getJson('/api/v1/quotations')->assertNotFound();
    });
});
