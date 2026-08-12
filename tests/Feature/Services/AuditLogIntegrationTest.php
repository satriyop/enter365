<?php

declare(strict_types=1);

use App\Models\Core\AuditLog;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Sales\InvoiceService;
use App\Services\Shared\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

/**
 * Create a draft invoice with items, then post it via the service.
 */
function createAndPostInvoice(): Invoice
{
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
    $invoice->refresh()->load('items');
    app(\App\Domain\Sales\Invoices\InvoiceDomainFactory::class)->applyTotals($invoice);
    $invoice->save();

    $service = app(InvoiceService::class);

    return $service->post($invoice);
}

describe('Service-level audit logging', function () {
    it('creates posted audit log when invoice is posted via service', function () {
        $invoice = createAndPostInvoice();

        $postedAudit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_POSTED)
            ->first();

        expect($postedAudit)->not->toBeNull()
            ->and($postedAudit->new_values)->toHaveKey('total_amount');
    });

    it('creates voided audit log when invoice is voided via service', function () {
        $invoice = createAndPostInvoice();
        $invoiceService = app(InvoiceService::class);

        $invoiceService->void($invoice, 'Test void reason');

        $voidAudit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_VOIDED)
            ->first();

        expect($voidAudit)->not->toBeNull()
            ->and($voidAudit->new_values['reason'])->toBe('Test void reason');
    });

    it('creates voided audit log when payment is voided', function () {
        $paymentService = app(PaymentService::class);
        $invoice = createSentInvoice();
        $cashAccount = \App\Models\Accounting\Account::where('code', '1-1000')->first();

        $payment = $paymentService->create([
            'type' => \App\Models\Shared\Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $cashAccount->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $paymentService->void($payment, 'Customer requested');

        $voidAudit = AuditLog::where('auditable_type', \App\Models\Shared\Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', AuditLog::ACTION_VOIDED)
            ->first();

        expect($voidAudit)->not->toBeNull()
            ->and($voidAudit->new_values['void_reason'])->toBe('Customer requested');
    });

    it('creates full audit trail for invoice lifecycle via service', function () {
        $invoice = createAndPostInvoice();

        $audits = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->orderBy('id')
            ->get();

        // Should have: created (from Auditable trait) + updated entries + posted (from service)
        expect($audits->where('action', AuditLog::ACTION_CREATED))->toHaveCount(1)
            ->and($audits->where('action', AuditLog::ACTION_POSTED))->toHaveCount(1);
    });
});
