<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\AccountLookupService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\Strategies\Closing\DirectClosingStrategy;
use App\Services\Accounting\Strategies\Closing\IncomeSummaryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Counter for unique entry numbers
    $this->entryCounter = 0;

    // Create required system accounts for closing strategies
    $this->retainedEarningsAccount = Account::factory()->create([
        'code' => '3-2000',
        'name' => 'Laba Ditahan',
        'type' => Account::TYPE_EQUITY,
        'is_system' => true,
    ]);

    $this->incomeSummaryAccount = Account::factory()->create([
        'code' => '3-9000',
        'name' => 'Ikhtisar Laba/Rugi',
        'type' => Account::TYPE_EQUITY,
        'is_system' => true,
    ]);

    $this->dividendsAccount = Account::factory()->create([
        'code' => '3-3000',
        'name' => 'Dividen/Prive',
        'type' => Account::TYPE_EQUITY,
        'is_system' => false,
    ]);

    // Create sample revenue and expense accounts
    $this->salesRevenueAccount = Account::factory()->revenue()->create([
        'code' => '4-1001',
        'name' => 'Pendapatan Penjualan',
    ]);

    $this->serviceRevenueAccount = Account::factory()->revenue()->create([
        'code' => '4-1002',
        'name' => 'Pendapatan Jasa',
    ]);

    $this->cogsAccount = Account::factory()->expense()->create([
        'code' => '5-1001',
        'name' => 'Harga Pokok Penjualan',
    ]);

    $this->salaryExpenseAccount = Account::factory()->expense()->create([
        'code' => '5-2001',
        'name' => 'Beban Gaji',
    ]);

    // Create a fiscal period (use current() factory to ensure it spans now())
    $this->fiscalPeriod = FiscalPeriod::factory()->current()->create();

    // Create a bank account for balancing entries
    $this->bankAccount = Account::factory()->asset()->create([
        'code' => '1-1002',
        'name' => 'Bank',
        'type' => Account::TYPE_ASSET,
    ]);

    // Initialize services
    $this->journalService = app(JournalService::class);
    $this->accountLookup = app(AccountLookupService::class);
    $this->directStrategy = new DirectClosingStrategy($this->journalService, $this->accountLookup);
    $this->incomeSummaryStrategy = new IncomeSummaryStrategy($this->journalService, $this->accountLookup);

    // Helper to generate unique entry numbers
    $this->generateEntryNumber = function (): string {
        $this->entryCounter++;

        return 'TEST-'.str_pad((string) $this->entryCounter, 6, '0', STR_PAD_LEFT);
    };
});

describe('DirectClosingStrategy', function () {

    describe('getIdentifier and getName', function () {
        it('returns correct identifier', function () {
            expect($this->directStrategy->getIdentifier())->toBe('direct');
        });

        it('returns correct name in Indonesian', function () {
            expect($this->directStrategy->getName())->toBe('Penutupan Langsung');
        });
    });

    describe('closeRevenueAccounts', function () {
        it('creates closing journal entry for revenue accounts', function () {
            // Create revenue transactions
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            // Create another revenue entry
            $serviceJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date->addDays(5),
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $serviceJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 3000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $serviceJournal->id,
                'account_id' => $this->serviceRevenueAccount->id,
                'debit' => 0,
                'credit' => 3000000,
            ]);

            $closingEntry = $this->directStrategy->closeRevenueAccounts($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-REV-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(3); // 2 revenue + 1 retained earnings

            // Check revenue accounts are debited
            $salesLine = $closingEntry->lines->firstWhere('account_id', $this->salesRevenueAccount->id);
            expect($salesLine)->not->toBeNull()
                ->and($salesLine->debit)->toBe(5000000)
                ->and($salesLine->credit)->toBe(0);

            $serviceLine = $closingEntry->lines->firstWhere('account_id', $this->serviceRevenueAccount->id);
            expect($serviceLine)->not->toBeNull()
                ->and($serviceLine->debit)->toBe(3000000)
                ->and($serviceLine->credit)->toBe(0);

            // Check retained earnings is credited with total
            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(0)
                ->and($retainedEarningsLine->credit)->toBe(8000000);
        });

        it('returns null when no revenue exists', function () {
            $closingEntry = $this->directStrategy->closeRevenueAccounts($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });

        it('skips revenue accounts with zero balance', function () {
            // Create revenue entry that nets to zero
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);

            // Reverse it
            $reversalJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date->addDays(1),
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $reversalJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $reversalJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);

            $closingEntry = $this->directStrategy->closeRevenueAccounts($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeExpenseAccounts', function () {
        it('creates closing journal entry for expense accounts', function () {
            // Create expense transactions
            $expenseJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->cogsAccount->id,
                'debit' => 3000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 3000000,
            ]);

            // Create another expense entry
            $salaryJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date->addDays(5),
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $salaryJournal->id,
                'account_id' => $this->salaryExpenseAccount->id,
                'debit' => 2000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $salaryJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 2000000,
            ]);

            $closingEntry = $this->directStrategy->closeExpenseAccounts($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-EXP-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(3); // 2 expense + 1 retained earnings

            // Check expense accounts are credited
            $cogsLine = $closingEntry->lines->firstWhere('account_id', $this->cogsAccount->id);
            expect($cogsLine)->not->toBeNull()
                ->and($cogsLine->debit)->toBe(0)
                ->and($cogsLine->credit)->toBe(3000000);

            $salaryLine = $closingEntry->lines->firstWhere('account_id', $this->salaryExpenseAccount->id);
            expect($salaryLine)->not->toBeNull()
                ->and($salaryLine->debit)->toBe(0)
                ->and($salaryLine->credit)->toBe(2000000);

            // Check retained earnings is debited with total
            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(5000000)
                ->and($retainedEarningsLine->credit)->toBe(0);
        });

        it('returns null when no expenses exist', function () {
            $closingEntry = $this->directStrategy->closeExpenseAccounts($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeIncomeSummary', function () {
        it('returns null as direct strategy does not use income summary', function () {
            $closingEntry = $this->directStrategy->closeIncomeSummary($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeDividends', function () {
        it('creates closing journal entry for dividends', function () {
            // Create dividend entry
            $dividendJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $dividendJournal->id,
                'account_id' => $this->dividendsAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $dividendJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);

            $closingEntry = $this->directStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-DIV-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(2);

            // Check dividends is credited
            $dividendsLine = $closingEntry->lines->firstWhere('account_id', $this->dividendsAccount->id);
            expect($dividendsLine)->not->toBeNull()
                ->and($dividendsLine->debit)->toBe(0)
                ->and($dividendsLine->credit)->toBe(1000000);

            // Check retained earnings is debited
            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(1000000)
                ->and($retainedEarningsLine->credit)->toBe(0);
        });

        it('returns null when no dividends exist', function () {
            $closingEntry = $this->directStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });

        it('returns null when dividends account does not exist', function () {
            // Delete dividends account
            $this->dividendsAccount->delete();

            // Clear cache to force lookup
            $this->accountLookup->clearCache();

            $closingEntry = $this->directStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });
});

describe('IncomeSummaryStrategy', function () {

    describe('getIdentifier and getName', function () {
        it('returns correct identifier', function () {
            expect($this->incomeSummaryStrategy->getIdentifier())->toBe('income_summary');
        });

        it('returns correct name in Indonesian', function () {
            expect($this->incomeSummaryStrategy->getName())->toBe('Ikhtisar Laba/Rugi');
        });
    });

    describe('closeRevenueAccounts', function () {
        it('creates closing journal entry to income summary', function () {
            // Create revenue transactions
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            $closingEntry = $this->incomeSummaryStrategy->closeRevenueAccounts($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-REV-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(2); // 1 revenue + 1 income summary

            // Check revenue is debited
            $revenueLine = $closingEntry->lines->firstWhere('account_id', $this->salesRevenueAccount->id);
            expect($revenueLine)->not->toBeNull()
                ->and($revenueLine->debit)->toBe(5000000)
                ->and($revenueLine->credit)->toBe(0);

            // Check income summary is credited
            $incomeSummaryLine = $closingEntry->lines->firstWhere('account_id', $this->incomeSummaryAccount->id);
            expect($incomeSummaryLine)->not->toBeNull()
                ->and($incomeSummaryLine->debit)->toBe(0)
                ->and($incomeSummaryLine->credit)->toBe(5000000);
        });

        it('returns null when no revenue exists', function () {
            $closingEntry = $this->incomeSummaryStrategy->closeRevenueAccounts($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeExpenseAccounts', function () {
        it('creates closing journal entry to income summary', function () {
            // Create expense transactions
            $expenseJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->cogsAccount->id,
                'debit' => 3000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 3000000,
            ]);

            $closingEntry = $this->incomeSummaryStrategy->closeExpenseAccounts($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-EXP-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(2); // 1 expense + 1 income summary

            // Check expense is credited
            $expenseLine = $closingEntry->lines->firstWhere('account_id', $this->cogsAccount->id);
            expect($expenseLine)->not->toBeNull()
                ->and($expenseLine->debit)->toBe(0)
                ->and($expenseLine->credit)->toBe(3000000);

            // Check income summary is debited
            $incomeSummaryLine = $closingEntry->lines->firstWhere('account_id', $this->incomeSummaryAccount->id);
            expect($incomeSummaryLine)->not->toBeNull()
                ->and($incomeSummaryLine->debit)->toBe(3000000)
                ->and($incomeSummaryLine->credit)->toBe(0);
        });

        it('returns null when no expenses exist', function () {
            $closingEntry = $this->incomeSummaryStrategy->closeExpenseAccounts($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeIncomeSummary', function () {
        it('transfers profit to retained earnings when credit balance exists', function () {
            // Create income summary credit balance (profit scenario)
            // First close revenue (creates credit in income summary)
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            // Close revenue to income summary
            $this->incomeSummaryStrategy->closeRevenueAccounts($this->fiscalPeriod);

            // Close expense to income summary
            $expenseJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->cogsAccount->id,
                'debit' => 2000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 2000000,
            ]);

            $this->incomeSummaryStrategy->closeExpenseAccounts($this->fiscalPeriod);

            // Now close income summary (should have profit of 3,000,000)
            $closingEntry = $this->incomeSummaryStrategy->closeIncomeSummary($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-IS-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(2);

            // Check income summary is debited (closing credit balance)
            $incomeSummaryLine = $closingEntry->lines->firstWhere('account_id', $this->incomeSummaryAccount->id);
            expect($incomeSummaryLine)->not->toBeNull()
                ->and($incomeSummaryLine->debit)->toBe(3000000)
                ->and($incomeSummaryLine->credit)->toBe(0);

            // Check retained earnings is credited (profit)
            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(0)
                ->and($retainedEarningsLine->credit)->toBe(3000000)
                ->and($retainedEarningsLine->description)->toContain('Laba');
        });

        it('transfers loss to retained earnings when debit balance exists', function () {
            // Create income summary debit balance (loss scenario)
            // Revenue less than expense
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 2000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 2000000,
            ]);

            $this->incomeSummaryStrategy->closeRevenueAccounts($this->fiscalPeriod);

            // Larger expense
            $expenseJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->cogsAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            $this->incomeSummaryStrategy->closeExpenseAccounts($this->fiscalPeriod);

            // Now close income summary (should have loss of 3,000,000)
            $closingEntry = $this->incomeSummaryStrategy->closeIncomeSummary($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->lines)->toHaveCount(2);

            // Check income summary is credited (closing debit balance)
            $incomeSummaryLine = $closingEntry->lines->firstWhere('account_id', $this->incomeSummaryAccount->id);
            expect($incomeSummaryLine)->not->toBeNull()
                ->and($incomeSummaryLine->debit)->toBe(0)
                ->and($incomeSummaryLine->credit)->toBe(3000000);

            // Check retained earnings is debited (loss)
            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(3000000)
                ->and($retainedEarningsLine->credit)->toBe(0)
                ->and($retainedEarningsLine->description)->toContain('Rugi');
        });

        it('returns null when income summary has zero balance', function () {
            $closingEntry = $this->incomeSummaryStrategy->closeIncomeSummary($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });

        it('returns null when income summary is exactly balanced', function () {
            // Create equal revenue and expense
            $revenueJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $this->salesRevenueAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            $this->incomeSummaryStrategy->closeRevenueAccounts($this->fiscalPeriod);

            $expenseJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->cogsAccount->id,
                'debit' => 5000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 5000000,
            ]);

            $this->incomeSummaryStrategy->closeExpenseAccounts($this->fiscalPeriod);

            $closingEntry = $this->incomeSummaryStrategy->closeIncomeSummary($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });

    describe('closeDividends', function () {
        it('creates closing journal entry for dividends', function () {
            // Create dividend entry
            $dividendJournal = JournalEntry::factory()->create([
                'entry_number' => ($this->generateEntryNumber)(),
                'fiscal_period_id' => $this->fiscalPeriod->id,
                'is_posted' => true,
                'entry_date' => $this->fiscalPeriod->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $dividendJournal->id,
                'account_id' => $this->dividendsAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $dividendJournal->id,
                'account_id' => $this->bankAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);

            $closingEntry = $this->incomeSummaryStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->not->toBeNull()
                ->and($closingEntry->source_type)->toBe(JournalEntry::SOURCE_CLOSING)
                ->and($closingEntry->is_posted)->toBeTrue()
                ->and($closingEntry->reference)->toBe("CLOSE-DIV-{$this->fiscalPeriod->id}")
                ->and($closingEntry->lines)->toHaveCount(2);

            $dividendsLine = $closingEntry->lines->firstWhere('account_id', $this->dividendsAccount->id);
            expect($dividendsLine)->not->toBeNull()
                ->and($dividendsLine->debit)->toBe(0)
                ->and($dividendsLine->credit)->toBe(1000000);

            $retainedEarningsLine = $closingEntry->lines->firstWhere('account_id', $this->retainedEarningsAccount->id);
            expect($retainedEarningsLine)->not->toBeNull()
                ->and($retainedEarningsLine->debit)->toBe(1000000)
                ->and($retainedEarningsLine->credit)->toBe(0);
        });

        it('returns null when no dividends exist', function () {
            $closingEntry = $this->incomeSummaryStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });

        it('returns null when dividends account does not exist', function () {
            // Delete dividends account
            $this->dividendsAccount->delete();

            // Clear cache to force lookup
            $this->accountLookup->clearCache();

            $closingEntry = $this->incomeSummaryStrategy->closeDividends($this->fiscalPeriod);

            expect($closingEntry)->toBeNull();
        });
    });
});
