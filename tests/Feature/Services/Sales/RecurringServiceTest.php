<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Shared\RecurringTemplate;
use App\Models\User;
use App\Services\Sales\RecurringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(RecurringService::class);
});

describe('RecurringService generate operations', function () {
    it('generates due documents from active templates', function () {
        $customer = Contact::factory()->customer()->create();

        // Create template that is due
        $template = RecurringTemplate::factory()->create([
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => $customer->id,
            'is_active' => true,
            'frequency' => 'monthly',
            'next_generate_date' => now()->subDay(),
            'items' => [
                [
                    'description' => 'Monthly Service',
                    'quantity' => 1,
                    'unit' => 'month',
                    'unit_price' => 100000,
                ],
            ],
        ]);

        $generated = $this->service->generateDueDocuments();

        expect($generated)->toHaveCount(1);
        expect($generated->first())->toBeInstanceOf(Invoice::class);

        $template->refresh();
        expect($template->occurrences_count)->toBe(1);
        expect($template->next_generate_date)->not->toBeNull();
    });

    it('skips inactive templates', function () {
        RecurringTemplate::factory()->create([
            'is_active' => false,
            'next_generate_date' => now()->subDay(),
        ]);

        $generated = $this->service->generateDueDocuments();

        expect($generated)->toHaveCount(0);
    });

    it('generates invoice with correct calculations', function () {
        $customer = Contact::factory()->customer()->create();

        $template = RecurringTemplate::factory()->create([
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => $customer->id,
            'is_active' => true,
            'start_date' => now()->subMonth(),
            'next_generate_date' => now()->subDay(),
            'tax_rate' => 11.00,
            'discount_amount' => 10000,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 50000, 'unit' => 'pcs'],
                ['description' => 'Item 2', 'quantity' => 1, 'unit_price' => 100000, 'unit' => 'pcs'],
            ],
        ]);

        $invoice = $this->service->generateFromTemplate($template);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($invoice->subtotal)->toBe(200000); // (2*50000) + (1*100000)
        expect($invoice->tax_amount)->toBe(22000); // 200000 * 11%
        expect($invoice->discount_amount)->toBe(10000);
        expect($invoice->total_amount)->toBe(212000); // 200000 + 22000 - 10000
        expect($invoice->items)->toHaveCount(2);
    });

    it('generates invoice from template without auto-post', function () {
        $customer = Contact::factory()->customer()->create();

        $template = RecurringTemplate::factory()->create([
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => $customer->id,
            'is_active' => true,
            'start_date' => now()->subMonth(),
            'next_generate_date' => now()->subDay(),
            'auto_post' => false,
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100000, 'unit' => 'month'],
            ],
        ]);

        $invoice = $this->service->generateFromTemplate($template);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($invoice->status)->toBe(DocumentStatus::Draft);
    });

    it('stops generating after reaching occurrences limit', function () {
        $customer = Contact::factory()->customer()->create();

        $template = RecurringTemplate::factory()->create([
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => $customer->id,
            'occurrences_limit' => 2,
            'occurrences_count' => 2,
            'next_generate_date' => now()->subDay(),
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100000, 'unit' => 'month'],
            ],
        ]);

        $generated = $this->service->generateDueDocuments();

        expect($generated)->toHaveCount(0);
    });
});

describe('RecurringService template creation', function () {
    it('creates template from existing invoice', function () {
        $customer = Contact::factory()->customer()->create();
        $invoice = Invoice::factory()->create(['contact_id' => $customer->id]);
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $template = $this->service->createTemplateFromInvoice($invoice, [
            'name' => 'Monthly Subscription',
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => now()->addMonth(),
        ]);

        expect($template)->toBeInstanceOf(RecurringTemplate::class);
        expect($template->type)->toBe(RecurringTemplate::TYPE_INVOICE);
        expect($template->contact_id)->toBe($customer->id);
        expect($template->items)->toHaveCount(2);
        expect($template->is_active)->toBeTrue();
    });

    it('creates template from bill', function () {
        $vendor = Contact::factory()->vendor()->create();
        $bill = \App\Models\Purchasing\Bill::factory()->create(['contact_id' => $vendor->id]);
        \App\Models\Purchasing\BillItem::factory()->create(['bill_id' => $bill->id]);

        $template = $this->service->createTemplateFromBill($bill, [
            'name' => 'Recurring Rent',
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => now()->addMonth(),
        ]);

        expect($template)->toBeInstanceOf(RecurringTemplate::class);
        expect($template->type)->toBe(RecurringTemplate::TYPE_BILL);
        expect($template->contact_id)->toBe($vendor->id);
    });
});
