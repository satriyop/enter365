<?php

namespace App\Models\Accounting;

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\FiscalPeriodStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property FiscalPeriodStatus|null $status
 * @property bool $is_closed
 * @property bool $is_locked
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property int|null $closing_entry_id
 * @property int|null $retained_earnings_amount
 * @property string|null $closing_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FiscalPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_closed',
        'is_locked',
        'closed_at',
        'closed_by',
        'closing_entry_id',
        'retained_earnings_amount',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FiscalPeriodStatus::class,
            'is_closed' => 'boolean',
            'is_locked' => 'boolean',
            'closed_at' => 'datetime',
            'retained_earnings_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FiscalPeriod $period): void {
            $status = $period->status ?? $period->statusFromLegacyBooleans();
            $period->status = $status;
            $period->is_closed = $status === FiscalPeriodStatus::Closed;
            $period->is_locked = in_array($status, [
                FiscalPeriodStatus::Locked,
                FiscalPeriodStatus::Closing,
                FiscalPeriodStatus::Closed,
            ], true);
        });
    }

    private function statusFromLegacyBooleans(): FiscalPeriodStatus
    {
        if ($this->is_closed) {
            return FiscalPeriodStatus::Closed;
        }

        if ($this->is_locked) {
            return FiscalPeriodStatus::Locked;
        }

        return FiscalPeriodStatus::Open;
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function closingEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }

    /**
     * Get the status enum value.
     *
     * Falls back to deriving from legacy boolean fields if status is not set.
     */
    public function getStatus(): FiscalPeriodStatus
    {
        // If status attribute is set, use it
        if ($this->status !== null) {
            return $this->status;
        }

        // Fallback: derive from legacy fields
        if ($this->is_closed) {
            return FiscalPeriodStatus::Closed;
        }

        if ($this->is_locked) {
            return FiscalPeriodStatus::Locked;
        }

        return FiscalPeriodStatus::Open;
    }

    /**
     * Get a state machine instance for this period.
     */
    public function stateMachine(): FiscalPeriodStateMachine
    {
        return new FiscalPeriodStateMachine($this);
    }

    /**
     * Check if a date falls within this fiscal period.
     */
    public function containsDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }

    /**
     * Check if period is open for transactions.
     */
    public function isOpen(): bool
    {
        return $this->getStatus() === FiscalPeriodStatus::Open;
    }

    /**
     * Check if period can be modified (not closed, may be locked).
     */
    public function canPost(): bool
    {
        return $this->getStatus() !== FiscalPeriodStatus::Closed;
    }

    /**
     * Get the current open fiscal period.
     */
    public static function current(): ?self
    {
        return static::query()
            ->where('status', FiscalPeriodStatus::Open)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Get the period for a specific date.
     */
    public static function forDate(\DateTimeInterface $date): ?self
    {
        return static::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Require an Open fiscal period for posting on $date.
     *
     * Missing / closed / locked periods are errors. POS tills and journal
     * create/post must share this guard so entries cannot land with
     * fiscal_period_id = null.
     */
    public static function assertOpenForPosting(\DateTimeInterface $date): self
    {
        $period = static::forDate($date);

        if ($period === null) {
            throw BusinessRuleException::operationNotAllowed(
                'periode fiskal',
                'Tidak ada periode fiskal untuk tanggal '.$date->format('Y-m-d').'.'
            );
        }

        if ($period->getStatus() === FiscalPeriodStatus::Closed) {
            throw BusinessRuleException::fiscalPeriodClosed($period->name);
        }

        if ($period->getStatus() === FiscalPeriodStatus::Locked) {
            throw BusinessRuleException::operationNotAllowed(
                'periode fiskal',
                "Periode fiskal '{$period->name}' sedang dikunci."
            );
        }

        return $period;
    }

    /**
     * Lock the period (prevent modifications but allow viewing).
     */
    public function lock(): bool
    {
        if ($this->getStatus() === FiscalPeriodStatus::Closed) {
            return false;
        }

        $this->update([
            'status' => FiscalPeriodStatus::Locked,
            'is_locked' => true,
        ]);

        return true;
    }

    /**
     * Unlock the period.
     */
    public function unlock(): bool
    {
        if ($this->getStatus() === FiscalPeriodStatus::Closed) {
            return false;
        }

        $this->update([
            'status' => FiscalPeriodStatus::Open,
            'is_locked' => false,
        ]);

        return true;
    }

    /**
     * Check if period can be closed.
     *
     * @return array{can_close: bool, errors: array<string>}
     */
    public function canClose(): array
    {
        $errors = [];

        // Check for unposted journal entries
        $unpostedCount = $this->journalEntries()->where('is_posted', false)->count();
        if ($unpostedCount > 0) {
            $errors[] = "Terdapat {$unpostedCount} jurnal yang belum diposting.";
        }

        // Check for draft invoices
        $draftInvoices = Invoice::query()
            ->where('status', DocumentStatus::Draft)
            ->whereBetween('invoice_date', [$this->start_date, $this->end_date])
            ->count();
        if ($draftInvoices > 0) {
            $errors[] = "Terdapat {$draftInvoices} faktur draft.";
        }

        // Check for draft bills
        $draftBills = Bill::query()
            ->where('status', DocumentStatus::Draft)
            ->whereBetween('bill_date', [$this->start_date, $this->end_date])
            ->count();
        if ($draftBills > 0) {
            $errors[] = "Terdapat {$draftBills} tagihan draft.";
        }

        return [
            'can_close' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get totals for revenue and expense accounts in this period.
     *
     * @return array{revenue: int, expense: int, net_income: int}
     */
    public function getIncomeStatement(): array
    {
        $revenue = 0;
        $expense = 0;

        $lines = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $this->id);
            })
            ->with('account')
            ->get();

        foreach ($lines as $line) {
            $account = $line->account;
            if ($account->type === Account::TYPE_REVENUE) {
                $revenue += ($line->credit - $line->debit);
            } elseif ($account->type === Account::TYPE_EXPENSE) {
                $expense += ($line->debit - $line->credit);
            }
        }

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'net_income' => $revenue - $expense,
        ];
    }

    /**
     * Create the next fiscal period.
     */
    public function createNextPeriod(): self
    {
        $nextStart = $this->end_date->copy()->addDay();
        $nextEnd = $nextStart->copy()->endOfYear();

        // Adjust if this is a mid-year start
        if ($this->start_date->month !== 1) {
            $nextEnd = $nextStart->copy()->addYear()->subDay();
        }

        $year = $nextStart->year;

        return static::create([
            'name' => "Tahun Fiskal {$year}",
            'start_date' => $nextStart,
            'end_date' => $nextEnd,
            'status' => FiscalPeriodStatus::Open,
            'is_closed' => false,
            'is_locked' => false,
        ]);
    }
}
