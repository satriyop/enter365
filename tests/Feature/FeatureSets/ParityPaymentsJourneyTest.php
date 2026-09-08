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

describe('parity feature set (payments and bank reconciliation)', function () {
    it('exposes payment list and bank transactions on the parity preset', function () {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.preset', 'parity')
            ->assertJsonPath('data.modules.payments', true)
            ->assertJsonPath('data.modules.bank_reconciliation', true)
            ->assertJsonPath('data.modules.invoices', true);

        $this->getJson('/api/v1/payments')->assertOk();
        $this->getJson('/api/v1/bank-transactions')->assertOk();
    });

    it('lets accountant Rina register a payment against a posted invoice', function () {
        Sanctum::actingAs($this->akuntan);

        $customer = Contact::query()->whereIn('type', ['customer', 'both'])->first()
            ?? Contact::factory()->customer()->create();
        $bankAccount = Account::query()->where('code', '1-1010')->firstOrFail();

        $create = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'description' => 'Parity payment invoice',
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

        $create->assertCreated();
        $invoiceId = (int) $create->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/post")->assertOk();

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

        $this->getJson('/api/v1/payments')->assertOk()
            ->assertJsonFragment(['id' => $pay->json('data.id')]);

        expect(Invoice::query()->findOrFail($invoiceId)->paid_amount)
            ->toBe((int) $create->json('data.total_amount'));
    });

    it('still 404s payments and bank transactions on the kasir-only pos preset', function () {
        applyFeaturePreset('pos');
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/payments')->assertNotFound();
        $this->getJson('/api/v1/bank-transactions')->assertNotFound();
    });

    it('turns bank reconciliation on under pos when FEATURE_BANK_RECONCILIATION is overridden', function () {
        applyFeaturePreset('pos');
        config([
            'features.modules.payments' => true,
            'features.modules.bank_reconciliation' => true,
        ]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/payments')->assertOk();
        $this->getJson('/api/v1/bank-transactions')->assertOk();
    });
});
