<?php

declare(strict_types=1);

use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\AuditLog;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Auditable trait', function () {
    it('logs audit entry when model is created', function () {
        $invoice = Invoice::factory()->create();

        $audit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit->action)->toBe(AuditLog::ACTION_CREATED)
            ->and($audit->new_values)->toBeArray()
            ->and($audit->new_values)->toHaveKey('invoice_number');
    });

    it('logs audit entry when model is updated', function () {
        $invoice = Invoice::factory()->create(['description' => 'Original']);

        $invoice->update(['description' => 'Updated']);

        $audit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_UPDATED)
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit->old_values)->toHaveKey('description')
            ->and($audit->old_values['description'])->toBe('Original')
            ->and($audit->new_values['description'])->toBe('Updated');
    });

    it('does not log audit when only excluded fields change', function () {
        $invoice = Invoice::factory()->create();

        // Clear the "created" audit log
        AuditLog::truncate();

        // Touch only updates timestamps which are excluded
        $invoice->touch();

        $updateAudit = AuditLog::where('auditable_type', Invoice::class)
            ->where('action', AuditLog::ACTION_UPDATED)
            ->first();

        expect($updateAudit)->toBeNull();
    });

    it('logs audit entry when model is deleted', function () {
        $invoice = Invoice::factory()->create();

        $invoiceId = $invoice->id;
        $invoice->delete();

        $audit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoiceId)
            ->where('action', AuditLog::ACTION_DELETED)
            ->first();

        expect($audit)->not->toBeNull();
    });

    it('records auditable_label for known models', function () {
        $invoice = Invoice::factory()->create();

        $audit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit->auditable_label)->toBe($invoice->invoice_number);
    });

    it('captures authenticated user on audit log', function () {
        $user = auth()->user();

        $invoice = Invoice::factory()->create();

        $audit = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit->user_id)->toBe($user->id)
            ->and($audit->user_name)->toBe($user->name);
    });

    it('works on Payment model', function () {
        $cashAccount = \App\Models\Accounting\Account::where('code', '1-1000')->first();
        $payment = Payment::factory()->withCashAccount($cashAccount)->create();

        $audit = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit)->not->toBeNull();
    });

    it('works on JournalEntry model', function () {
        $entry = JournalEntry::factory()->create();

        $audit = AuditLog::where('auditable_type', JournalEntry::class)
            ->where('auditable_id', $entry->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit)->not->toBeNull();
    });

    it('works on FiscalPeriod model', function () {
        $period = FiscalPeriod::factory()->create();

        $audit = AuditLog::where('auditable_type', FiscalPeriod::class)
            ->where('auditable_id', $period->id)
            ->where('action', AuditLog::ACTION_CREATED)
            ->first();

        expect($audit)->not->toBeNull();
    });
});
