<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\EmployeeExpenseServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Shared\DocumentNumbers;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\EmployeeExpense;
use App\Models\Accounting\JournalEntry;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;

class EmployeeExpenseService extends BaseService implements EmployeeExpenseServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalServiceInterface $journalService,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeExpense
    {
        return $this->executeInTransaction('create_employee_expense', function () use ($data) {
            $payload = $this->payload($data);

            return EmployeeExpense::query()->create([
                'expense_number' => DocumentNumbers::generate(
                    'EXP-'.now()->format('Ym').'-',
                    'employee_expenses',
                    'expense_number',
                ),
                'employee_id' => $payload['employee_id'],
                'contact_id' => $payload['contact_id'] ?? null,
                'expense_date' => $payload['expense_date'],
                'description' => $payload['description'],
                'amount' => $payload['amount'],
                'tax_amount' => $payload['tax_amount'],
                'total_amount' => $payload['total_amount'],
                'expense_account_id' => $payload['expense_account_id'],
                'notes' => $payload['notes'] ?? null,
                'status' => EmployeeExpense::STATUS_DRAFT,
                'created_by' => $this->getUserId(),
            ]);
        }, ['employee_id' => $data['employee_id'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeExpense $expense, array $data): EmployeeExpense
    {
        return $this->executeInTransaction('update_employee_expense', function () use ($expense, $data) {
            if (! $expense->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan draf yang bisa diubah.',
                    ['employee_expense_id' => $expense->id]
                );
            }

            $expense->update($this->payload($data, $expense));

            return $expense->fresh(['employee', 'contact', 'expenseAccount']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    public function delete(EmployeeExpense $expense): void
    {
        $this->executeInTransaction('delete_employee_expense', function () use ($expense) {
            if (! $expense->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan draf yang bisa dihapus.',
                    ['employee_expense_id' => $expense->id]
                );
            }
            $expense->delete();
        }, ['employee_expense_id' => $expense->id]);
    }

    public function submit(EmployeeExpense $expense): EmployeeExpense
    {
        return $this->executeInTransaction('submit_employee_expense', function () use ($expense) {
            $expense = EmployeeExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! $expense->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan draf yang bisa diajukan.',
                    ['employee_expense_id' => $expense->id]
                );
            }
            $expense->update(['status' => EmployeeExpense::STATUS_SUBMITTED]);

            return $expense->fresh(['employee', 'contact', 'expenseAccount']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    public function approve(EmployeeExpense $expense): EmployeeExpense
    {
        return $this->executeInTransaction('approve_employee_expense', function () use ($expense) {
            $expense = EmployeeExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! $expense->isSubmitted()) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan yang sudah diajukan yang bisa disetujui.',
                    ['employee_expense_id' => $expense->id]
                );
            }
            $expense->update(['status' => EmployeeExpense::STATUS_APPROVED]);

            return $expense->fresh(['employee', 'contact', 'expenseAccount']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    public function refuse(EmployeeExpense $expense, ?string $reason = null): EmployeeExpense
    {
        return $this->executeInTransaction('refuse_employee_expense', function () use ($expense, $reason) {
            $expense = EmployeeExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! in_array($expense->status, [EmployeeExpense::STATUS_DRAFT, EmployeeExpense::STATUS_SUBMITTED], true)) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan draf atau diajukan yang bisa ditolak.',
                    ['employee_expense_id' => $expense->id]
                );
            }
            $notes = $expense->notes;
            if (filled($reason)) {
                $notes = trim((string) $notes."\n".$reason);
            }
            $expense->update([
                'status' => EmployeeExpense::STATUS_REFUSED,
                'notes' => $notes,
            ]);

            return $expense->fresh(['employee', 'contact', 'expenseAccount']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    public function post(EmployeeExpense $expense): EmployeeExpense
    {
        return $this->executeInTransaction('post_employee_expense', function () use ($expense) {
            $expense = EmployeeExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! $expense->isApproved()) {
                throw new BusinessRuleException(
                    'Hanya biaya karyawan yang sudah disetujui yang bisa diposting.',
                    ['employee_expense_id' => $expense->id]
                );
            }

            $total = (int) $expense->total_amount;
            $tax = (int) $expense->tax_amount;
            $base = (int) $expense->amount;
            $apCode = (string) config('accounting.default_accounts.accounts_payable');
            $taxCode = (string) config('accounting.default_accounts.tax_receivable');

            $lines = [
                [
                    'account_id' => $expense->expense_account_id,
                    'description' => $expense->description,
                    'debit' => $base,
                    'credit' => 0,
                ],
            ];
            if ($tax > 0) {
                $lines[] = [
                    'account_code' => $taxCode,
                    'description' => 'PPN masukan '.$expense->expense_number,
                    'debit' => $tax,
                    'credit' => 0,
                ];
            }
            $lines[] = [
                'account_code' => $apCode,
                'description' => 'Hutang biaya karyawan '.$expense->expense_number,
                'debit' => 0,
                'credit' => $total,
            ];

            $entry = $this->journalService->createEntry([
                'entry_date' => $expense->expense_date->toDateString(),
                'description' => 'Biaya karyawan '.$expense->expense_number,
                'reference' => $expense->expense_number,
                'source_type' => JournalEntry::SOURCE_EMPLOYEE_EXPENSE,
                'source_id' => $expense->id,
                'lines' => $lines,
            ], autoPost: true);

            $expense->update([
                'status' => EmployeeExpense::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
            ]);

            return $expense->fresh(['employee', 'contact', 'expenseAccount', 'journalEntry']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    public function cancel(EmployeeExpense $expense, ?string $reason = null): EmployeeExpense
    {
        return $this->executeInTransaction('cancel_employee_expense', function () use ($expense, $reason) {
            $expense = EmployeeExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status === EmployeeExpense::STATUS_CANCELLED) {
                throw new BusinessRuleException(
                    'Biaya karyawan ini sudah dibatalkan.',
                    ['employee_expense_id' => $expense->id]
                );
            }
            if ($expense->isPosted()) {
                $entry = $expense->journalEntry;
                if ($entry !== null) {
                    $this->journalService->reverseEntry(
                        $entry,
                        filled($reason) ? $reason : 'Pembatalan biaya karyawan '.$expense->expense_number
                    );
                }
            } elseif (! in_array($expense->status, [
                EmployeeExpense::STATUS_DRAFT,
                EmployeeExpense::STATUS_SUBMITTED,
                EmployeeExpense::STATUS_APPROVED,
                EmployeeExpense::STATUS_REFUSED,
            ], true)) {
                throw new BusinessRuleException(
                    'Biaya karyawan ini tidak bisa dibatalkan.',
                    ['employee_expense_id' => $expense->id]
                );
            }

            $expense->update(['status' => EmployeeExpense::STATUS_CANCELLED]);

            return $expense->fresh(['employee', 'contact', 'expenseAccount', 'journalEntry']) ?? $expense;
        }, ['employee_expense_id' => $expense->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?EmployeeExpense $existing = null): array
    {
        $payload = Arr::only($data, [
            'employee_id',
            'contact_id',
            'expense_date',
            'description',
            'amount',
            'tax_amount',
            'expense_account_id',
            'notes',
        ]);

        $amount = array_key_exists('amount', $payload)
            ? (int) $payload['amount']
            : (int) ($existing instanceof EmployeeExpense ? $existing->amount : 0);
        $tax = array_key_exists('tax_amount', $payload)
            ? (int) $payload['tax_amount']
            : (int) ($existing instanceof EmployeeExpense ? $existing->tax_amount : 0);
        if ($amount <= 0) {
            throw new BusinessRuleException('Jumlah biaya harus lebih dari 0.');
        }
        if ($tax < 0) {
            throw new BusinessRuleException('PPN tidak boleh negatif.');
        }
        $payload['amount'] = $amount;
        $payload['tax_amount'] = $tax;
        $payload['total_amount'] = $amount + $tax;

        return $payload;
    }
}
