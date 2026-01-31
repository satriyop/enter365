<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(InvoicePaymentService::class);
});

describe('InvoicePaymentService - recordPayment', function () {

    it('records payment and updates paid_amount', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $result = $this->service->recordPayment($invoice, 300000);

        expect($result->paid_amount)->toBe(300000);
    });

    it('transitions status to partial on partial payment', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $result = $this->service->recordPayment($invoice, 500000);

        expect($result->status)->toBe(DocumentStatus::Partial);
    });

    it('transitions status to paid on full payment', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $result = $this->service->recordPayment($invoice, 1000000);

        expect($result->status)->toBe(DocumentStatus::Paid);
    });

    it('can record multiple payments', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $this->service->recordPayment($invoice, 300000);
        $this->service->recordPayment($invoice, 200000);
        $result = $this->service->recordPayment($invoice, 500000);

        expect($result->paid_amount)->toBe(1000000)
            ->and($result->status)->toBe(DocumentStatus::Paid);
    });

});

describe('InvoicePaymentService - reversePayment', function () {

    it('reverses payment and decreases paid_amount', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 600000]);

        $result = $this->service->reversePayment($invoice, 200000);

        expect($result->paid_amount)->toBe(400000);
    });

    it('transitions status from paid to partial after reversal', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
            'status' => DocumentStatus::Paid,
        ]);

        $result = $this->service->reversePayment($invoice, 300000);

        expect($result->status)->toBe(DocumentStatus::Partial)
            ->and($result->paid_amount)->toBe(700000);
    });

    it('transitions status to sent when all payments reversed', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 500000,
            'status' => DocumentStatus::Partial,
        ]);

        $result = $this->service->reversePayment($invoice, 500000);

        expect($result->status)->toBe(DocumentStatus::Sent)
            ->and($result->paid_amount)->toBe(0);
    });

    it('does not allow negative paid_amount', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 200000]);

        $result = $this->service->reversePayment($invoice, 500000);

        expect($result->paid_amount)->toBe(0);
    });

});

describe('InvoicePaymentService - updatePaymentStatus', function () {

    it('updates status based on paid_amount', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $invoice->paid_amount = 400000;
        $invoice->save();

        $result = $this->service->updatePaymentStatus($invoice);

        expect($result->status)->toBe(DocumentStatus::Partial);
    });

    it('sets status to paid when fully paid', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $invoice->paid_amount = 1000000;
        $invoice->save();

        $result = $this->service->updatePaymentStatus($invoice);

        expect($result->status)->toBe(DocumentStatus::Paid);
    });

});

describe('InvoicePaymentService - markAsOverdue', function () {

    it('marks sent invoice as overdue', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'due_date' => now()->subDays(5),
            'status' => DocumentStatus::Sent,
        ]);

        $result = $this->service->markAsOverdue($invoice);

        expect($result)->toBeTrue();

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Overdue);
    });

    it('marks partial invoice as overdue', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'due_date' => now()->subDays(5),
            'status' => DocumentStatus::Partial,
        ]);

        $result = $this->service->markAsOverdue($invoice);

        expect($result)->toBeTrue();
    });

    it('does not mark paid invoice as overdue', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'due_date' => now()->subDays(5),
            'status' => DocumentStatus::Paid,
        ]);

        $result = $this->service->markAsOverdue($invoice);

        expect($result)->toBeFalse();
    });

    it('does not mark cancelled invoice as overdue', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'due_date' => now()->subDays(5),
            'status' => DocumentStatus::Cancelled,
        ]);

        $result = $this->service->markAsOverdue($invoice);

        expect($result)->toBeFalse();
    });

    it('does not mark draft invoice as overdue', function () {
        $invoice = Invoice::factory()->draft()->create([
            'due_date' => now()->subDays(5),
        ]);

        $result = $this->service->markAsOverdue($invoice);

        expect($result)->toBeFalse();
    });

});

describe('InvoicePaymentService - early payment discount', function () {

    it('calculates early payment discount', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'early_discount_percent' => 5,
            'early_discount_days' => 10,
        ]);

        $discount = $this->service->calculateEarlyDiscount($invoice);

        expect($discount)->toBe(50000); // 5% of 1,000,000
    });

    it('returns zero discount when no early discount configured', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'early_discount_percent' => 0,
            'early_discount_days' => 0,
        ]);

        $discount = $this->service->calculateEarlyDiscount($invoice);

        expect($discount)->toBe(0);
    });

    it('calculates early payment total with discount', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'early_discount_percent' => 10,
            'early_discount_days' => 7,
        ]);

        $total = $this->service->getEarlyPaymentTotal($invoice);

        expect($total)->toBe(900000); // 1,000,000 - 100,000
    });

});

describe('InvoicePaymentService - getPaymentSummary', function () {

    it('returns complete payment summary', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 400000,
            'status' => DocumentStatus::Partial,
            'due_date' => now()->subDays(3),
        ]);

        $summary = $this->service->getPaymentSummary($invoice);

        expect($summary)->toBeArray()
            ->and($summary['total'])->toBe(1000000)
            ->and($summary['paid'])->toBe(400000)
            ->and($summary['outstanding'])->toBe(600000)
            ->and($summary['status'])->toBe(DocumentStatus::Partial)
            ->and($summary['is_overdue'])->toBeTrue()
            ->and($summary['days_overdue'])->toBeGreaterThan(0);
    });

    it('includes early discount information in summary', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'early_discount_percent' => 5,
            'early_discount_days' => 10,
            'invoice_date' => now()->subDays(2),
        ]);

        $summary = $this->service->getPaymentSummary($invoice);

        expect($summary['early_discount_available'])->toBeTrue()
            ->and($summary['early_discount_amount'])->toBe(50000);
    });

    it('shows zero outstanding for fully paid invoice', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
            'status' => DocumentStatus::Paid,
        ]);

        $summary = $this->service->getPaymentSummary($invoice);

        expect($summary['outstanding'])->toBe(0)
            ->and($summary['status'])->toBe(DocumentStatus::Paid);
    });

    it('shows correct overdue status', function () {
        $invoice = createSentInvoice();
        $invoice->update([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(5),
        ]);

        $summary = $this->service->getPaymentSummary($invoice);

        expect($summary['is_overdue'])->toBeFalse()
            ->and($summary['days_overdue'])->toBe(0);
    });

});

describe('InvoicePaymentService - edge cases', function () {

    it('handles overpayment scenario', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        // Record overpayment
        $invoice->paid_amount = 1200000;
        $invoice->save();

        $result = $this->service->updatePaymentStatus($invoice);

        expect($result->status)->toBe(DocumentStatus::Paid);
    });

    it('handles zero amount invoice', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 0, 'paid_amount' => 0]);

        $summary = $this->service->getPaymentSummary($invoice);

        expect($summary['total'])->toBe(0)
            ->and($summary['outstanding'])->toBe(0);
    });

    it('handles multiple partial payments correctly', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        // First payment
        $this->service->recordPayment($invoice, 200000);
        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Partial);

        // Second payment
        $this->service->recordPayment($invoice, 300000);
        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Partial)
            ->and($invoice->paid_amount)->toBe(500000);

        // Final payment
        $this->service->recordPayment($invoice, 500000);
        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Paid)
            ->and($invoice->paid_amount)->toBe(1000000);
    });

});
