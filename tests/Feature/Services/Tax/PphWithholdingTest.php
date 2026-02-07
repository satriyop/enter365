<?php

declare(strict_types=1);

use App\Enums\PphCategory;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Shared\Payment;
use App\Services\Shared\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Enable PPh
    config(['accounting.pph.enabled' => true]);

    // Run PPh accounts migration seeder
    $parentAccount = Account::where('code', '2-1300')->first();
    if ($parentAccount) {
        if (! Account::where('code', '2-1305')->exists()) {
            Account::create([
                'code' => '2-1305',
                'name' => 'Utang PPh 4(2)',
                'type' => Account::TYPE_LIABILITY,
                'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
                'parent_id' => $parentAccount->id,
                'is_system' => false,
            ]);
        }
        if (! Account::where('code', '2-1306')->exists()) {
            Account::create([
                'code' => '2-1306',
                'name' => 'Utang PPh 26',
                'type' => Account::TYPE_LIABILITY,
                'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
                'parent_id' => $parentAccount->id,
                'is_system' => false,
            ]);
        }
    }

    $this->service = app(PaymentService::class);
    $this->cashAccount = Account::where('code', '1-1000')->first();
});

/**
 * Helper to assert trial balance is balanced.
 */
function assertTrialBalanceIsBalanced(): void
{
    $totals = DB::table('journal_entry_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
        ->where('journal_entries.is_reversed', false)
        ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
        ->first();

    expect((int) $totals->total_debit)->toBe((int) $totals->total_credit,
        "Trial balance unbalanced: Debit={$totals->total_debit}, Credit={$totals->total_credit}"
    );
}

describe('PPh Withholding - Full Payment Flow', function () {

    it('creates payment with PPh withholding and correct JE lines', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'status' => 'received',
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'tax_amount' => 1_100_000,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'pph_withheld_amount' => 0,
                'paid_amount' => 0,
            ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 11_100_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        // Payment should have PPh fields populated
        expect($payment->pph_category)->toBe(PphCategory::Pph23Jasa)
            ->and($payment->pph_amount)->toBe(200_000)
            ->and($payment->getCashDisbursement())->toBe(10_900_000)
            ->and($payment->hasPphWithholding())->toBeTrue();

        // JE should have 3 lines: Dr AP, Cr Cash (reduced), Cr PPh Payable
        $je = $payment->journalEntry()->with('lines.account')->first();
        expect($je->lines)->toHaveCount(3);

        $lines = $je->lines->sortBy('account_id');
        $debitTotal = $lines->sum('debit');
        $creditTotal = $lines->sum('credit');
        expect($debitTotal)->toBe($creditTotal, 'JE must be balanced');

        // Find the PPh payable line
        $pphLine = $je->lines->first(fn ($l) => $l->account->code === '2-1302');
        expect($pphLine)->not->toBeNull()
            ->and($pphLine->credit)->toBe(200_000);

        // Cash line should be reduced
        $cashLine = $je->lines->first(fn ($l) => $l->account_id === $this->cashAccount->id);
        expect($cashLine->credit)->toBe(10_900_000);

        // AP line should still be full amount
        $apLine = $je->lines->first(fn ($l) => $l->account->code === '2-1100');
        expect($apLine->debit)->toBe(11_100_000);

        // Bill PPh withheld should be updated
        $bill->refresh();
        expect($bill->pph_withheld_amount)->toBe(200_000);

        assertTrialBalanceIsBalanced();
    });

    it('skips PPh when feature is disabled', function () {
        config(['accounting.pph.enabled' => false]);

        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'status' => 'received',
                'total_amount' => 11_100_000,
                'paid_amount' => 0,
            ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 11_100_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        expect($payment->hasPphWithholding())->toBeFalse()
            ->and((int) $payment->pph_amount)->toBe(0);

        // JE should have only 2 lines (Dr AP, Cr Cash) - no PPh line
        $je = $payment->journalEntry()->with('lines')->first();
        expect($je->lines)->toHaveCount(2);

        assertTrialBalanceIsBalanced();
    });

    it('reverses PPh on payment void', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'status' => 'received',
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'pph_withheld_amount' => 0,
                'paid_amount' => 0,
            ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 11_100_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        // Verify PPh was withheld
        $bill->refresh();
        expect($bill->pph_withheld_amount)->toBe(200_000);

        // Void payment
        $this->service->void($payment, 'Test void');

        // PPh withheld should be reversed
        $bill->refresh();
        expect($bill->pph_withheld_amount)->toBe(0)
            ->and($bill->paid_amount)->toBe(0);

        assertTrialBalanceIsBalanced();
    });

    it('handles partial payment with proportional PPh', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'status' => 'received',
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'pph_withheld_amount' => 0,
                'paid_amount' => 0,
            ]);

        // Pay half
        $payment1 = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 5_550_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        expect($payment1->pph_amount)->toBe(100_000); // proportional
        $bill->refresh();
        expect($bill->pph_withheld_amount)->toBe(100_000);

        // Pay remaining
        $payment2 = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 5_550_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        expect($payment2->pph_amount)->toBe(100_000); // remaining
        $bill->refresh();
        expect($bill->pph_withheld_amount)->toBe(200_000);

        assertTrialBalanceIsBalanced();
    });

    it('allows explicit opt-out of PPh withholding', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'status' => 'received',
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'paid_amount' => 0,
            ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 11_100_000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
            'pph_withhold' => false,
        ]);

        expect($payment->hasPphWithholding())->toBeFalse();
        assertTrialBalanceIsBalanced();
    });

});
