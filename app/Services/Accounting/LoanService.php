<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\LoanServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanLine;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LoanService extends BaseService implements LoanServiceInterface
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
    public function create(array $data): Loan
    {
        return $this->executeInTransaction('create_loan', function () use ($data) {
            $this->assertPrincipal($data);
            $data['created_by'] = $this->getUserId();
            $data['status'] = Loan::STATUS_DRAFT;
            $data['remaining_principal'] = 0;

            return Loan::query()->create(Arr::only($data, [
                'code',
                'name',
                'contact_id',
                'principal',
                'annual_interest_rate',
                'duration_months',
                'start_date',
                'liability_account_id',
                'interest_account_id',
                'bank_account_id',
                'journal_id',
                'status',
                'remaining_principal',
                'notes',
                'created_by',
            ]));
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Loan $loan, array $data): Loan
    {
        return $this->executeInTransaction('update_loan', function () use ($loan, $data) {
            if (! $loan->isDraft()) {
                throw new BusinessRuleException(
                    'Pinjaman yang sudah berjalan tidak bisa diubah.',
                    ['loan_id' => $loan->id]
                );
            }

            $merged = array_merge($loan->only(['principal', 'duration_months']), $data);
            $this->assertPrincipal($merged);
            $loan->update($data);

            return $loan->fresh(['lines', 'contact']) ?? $loan;
        }, ['loan_id' => $loan->id]);
    }

    public function delete(Loan $loan): void
    {
        $this->executeInTransaction('delete_loan', function () use ($loan) {
            if (! $loan->isDraft()) {
                throw new BusinessRuleException(
                    'Pinjaman yang sudah berjalan tidak bisa dihapus.',
                    ['loan_id' => $loan->id]
                );
            }

            $loan->lines()->delete();
            $loan->delete();
        }, ['loan_id' => $loan->id]);
    }

    public function confirm(Loan $loan): Loan
    {
        return $this->executeInTransaction('confirm_loan', function () use ($loan) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if (! $loan->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya pinjaman draf yang bisa dikonfirmasi.',
                    ['loan_id' => $loan->id]
                );
            }

            $this->rebuildSchedule($loan);

            $disbursement = $this->createLoanEntry($loan, [
                'entry_date' => Carbon::parse($loan->start_date)->toDateString(),
                'description' => 'Pencairan pinjaman '.$loan->code.' '.$loan->name,
                'lines' => [
                    [
                        'account_id' => $loan->bank_account_id,
                        'debit' => $loan->principal,
                        'credit' => 0,
                        'description' => 'Pencairan '.$loan->code,
                    ],
                    [
                        'account_id' => $loan->liability_account_id,
                        'debit' => 0,
                        'credit' => $loan->principal,
                        'description' => 'Utang '.$loan->code,
                    ],
                ],
            ]);

            $loan->update([
                'status' => Loan::STATUS_RUNNING,
                'remaining_principal' => $loan->principal,
                'disbursement_journal_entry_id' => $disbursement->id,
            ]);

            return $loan->fresh(['lines', 'contact']) ?? $loan;
        }, ['loan_id' => $loan->id]);
    }

    public function postNextInstallment(Loan $loan): Loan
    {
        return $this->executeInTransaction('post_loan_installment', function () use ($loan) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if (! $loan->isRunning()) {
                throw new BusinessRuleException(
                    'Hanya pinjaman berjalan yang bisa diposting angsurannya.',
                    ['loan_id' => $loan->id]
                );
            }

            $line = $loan->lines()
                ->where('status', LoanLine::STATUS_DRAFT)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                throw new BusinessRuleException(
                    'Tidak ada angsuran yang menunggu posting.',
                    ['loan_id' => $loan->id]
                );
            }

            $entry = $this->createLoanEntry($loan, [
                'entry_date' => Carbon::parse($line->due_date)->toDateString(),
                'description' => 'Angsuran '.$loan->code.' #'.$line->sequence,
                'lines' => [
                    [
                        'account_id' => $loan->liability_account_id,
                        'debit' => $line->principal_amount,
                        'credit' => 0,
                        'description' => 'Pokok '.$loan->code,
                    ],
                    [
                        'account_id' => $loan->interest_account_id,
                        'debit' => $line->interest_amount,
                        'credit' => 0,
                        'description' => 'Bunga '.$loan->code,
                    ],
                    [
                        'account_id' => $loan->bank_account_id,
                        'debit' => 0,
                        'credit' => $line->payment_amount,
                        'description' => 'Pembayaran angsuran '.$loan->code,
                    ],
                ],
            ]);

            $remaining = max(0, (int) $loan->remaining_principal - (int) $line->principal_amount);
            $line->update([
                'status' => LoanLine::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ]);

            $updates = ['remaining_principal' => $remaining];
            if ($remaining === 0) {
                $updates['status'] = Loan::STATUS_CLOSED;
            }
            $loan->update($updates);

            return $loan->fresh(['lines', 'contact']) ?? $loan;
        }, ['loan_id' => $loan->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function analysis(): array
    {
        $rows = DB::table('loans')
            ->select('status')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('COALESCE(SUM(principal), 0) as total_principal')
            ->selectRaw('COALESCE(SUM(remaining_principal), 0) as remaining_principal')
            ->groupBy('status')
            ->get();

        $interestPosted = (int) DB::table('loan_lines')
            ->where('status', LoanLine::STATUS_POSTED)
            ->sum('interest_amount');
        $interestRemaining = (int) DB::table('loan_lines')
            ->where('status', LoanLine::STATUS_DRAFT)
            ->sum('interest_amount');

        $upcoming = DB::table('loan_lines')
            ->join('loans', 'loans.id', '=', 'loan_lines.loan_id')
            ->where('loan_lines.status', LoanLine::STATUS_DRAFT)
            ->orderBy('loan_lines.due_date')
            ->orderBy('loan_lines.sequence')
            ->limit(100)
            ->get([
                'loan_lines.id',
                'loan_lines.loan_id',
                'loans.code as loan_code',
                'loans.name as loan_name',
                'loan_lines.sequence',
                'loan_lines.due_date',
                'loan_lines.principal_amount',
                'loan_lines.interest_amount',
                'loan_lines.payment_amount',
                'loan_lines.remaining_principal',
                'loan_lines.status',
            ]);

        return [
            'loan_count' => (int) $rows->sum('loan_count'),
            'running_count' => (int) data_get($rows->firstWhere('status', Loan::STATUS_RUNNING), 'loan_count', 0),
            'total_principal' => (int) $rows->sum('total_principal'),
            'remaining_principal' => (int) $rows->sum('remaining_principal'),
            'interest_posted' => $interestPosted,
            'interest_remaining' => $interestRemaining,
            'by_status' => $rows->map(fn ($row): array => [
                'status' => $row->status,
                'loan_count' => (int) $row->loan_count,
                'total_principal' => (int) $row->total_principal,
                'remaining_principal' => (int) $row->remaining_principal,
            ])->values()->all(),
            'upcoming' => $upcoming->map(fn ($row): array => [
                'id' => (int) $row->id,
                'loan_id' => (int) $row->loan_id,
                'loan_code' => $row->loan_code,
                'loan_name' => $row->loan_name,
                'sequence' => (int) $row->sequence,
                'due_date' => $row->due_date,
                'principal_amount' => (int) $row->principal_amount,
                'interest_amount' => (int) $row->interest_amount,
                'payment_amount' => (int) $row->payment_amount,
                'remaining_principal' => (int) $row->remaining_principal,
                'status' => $row->status,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPrincipal(array $data): void
    {
        if ((int) ($data['principal'] ?? 0) <= 0) {
            throw new BusinessRuleException('Pokok pinjaman harus lebih dari 0.');
        }
        if ((int) ($data['duration_months'] ?? 0) < 1) {
            throw new BusinessRuleException('Tenor pinjaman minimal 1 bulan.');
        }
    }

    /**
     * @param  array{entry_date: string, description: string, lines: list<array<string, mixed>>}  $payload
     */
    private function createLoanEntry(Loan $loan, array $payload): JournalEntry
    {
        $payload['reference'] = $loan->code;
        $payload['source_type'] = JournalEntry::SOURCE_LOAN;
        $payload['source_id'] = $loan->id;
        if ($loan->journal_id) {
            $payload['journal_id'] = $loan->journal_id;
        }

        $payload['lines'] = array_values(array_filter(
            $payload['lines'],
            fn (array $line): bool => ((int) ($line['debit'] ?? 0) + (int) ($line['credit'] ?? 0)) > 0
        ));

        return $this->journalService->createEntry($payload, true);
    }

    private function rebuildSchedule(Loan $loan): void
    {
        $loan->lines()->delete();

        $principal = (int) $loan->principal;
        $months = (int) $loan->duration_months;
        $monthlyRate = ((float) $loan->annual_interest_rate) / 12 / 100;
        $date = Carbon::parse($loan->start_date)->endOfMonth();
        $remaining = $principal;

        $payment = $monthlyRate <= 0.0
            ? intdiv($principal, $months)
            : (int) round($principal * $monthlyRate * ((1 + $monthlyRate) ** $months) / (((1 + $monthlyRate) ** $months) - 1));

        for ($i = 1; $i <= $months; $i++) {
            if ($monthlyRate <= 0.0) {
                $interest = 0;
                $principalPart = $i === $months ? $remaining : $payment;
            } else {
                $interest = (int) round($remaining * $monthlyRate);
                $principalPart = $i === $months ? $remaining : min($remaining, max(0, $payment - $interest));
            }

            $remaining -= $principalPart;
            $loan->lines()->create([
                'sequence' => $i,
                'due_date' => $date->toDateString(),
                'principal_amount' => $principalPart,
                'interest_amount' => $interest,
                'payment_amount' => $principalPart + $interest,
                'remaining_principal' => $remaining,
                'status' => LoanLine::STATUS_DRAFT,
            ]);

            $date = $date->copy()->addMonthNoOverflow()->endOfMonth();
        }
    }
}
