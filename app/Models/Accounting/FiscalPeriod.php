<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
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
            'is_closed' => 'boolean',
            'is_locked' => 'boolean',
            'closed_at' => 'datetime',
            'retained_earnings_amount' => 'integer',
        ];
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
        return ! $this->is_closed && ! $this->is_locked;
    }

    /**
     * Check if period can be modified (not closed, may be locked).
     */
    public function canPost(): bool
    {
        return ! $this->is_closed;
    }

    /**
     * Get the current open fiscal period.
     */
    public static function current(): ?self
    {
        return static::query()
            ->where('is_closed', false)
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
     * Lock the period (prevent modifications but allow viewing).
     */
    public function lock(): bool
    {
        if ($this->is_closed) {
            return false;
        }

        $this->update(['is_locked' => true]);

        return true;
    }

    /**
     * Unlock the period.
     */
    public function unlock(): bool
    {
        if ($this->is_closed) {
            return false;
        }

        $this->update(['is_locked' => false]);

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
            ->where('status', Invoice::STATUS_DRAFT)
            ->whereBetween('invoice_date', [$this->start_date, $this->end_date])
            ->count();
        if ($draftInvoices > 0) {
            $errors[] = "Terdapat {$draftInvoices} faktur draft.";
        }

        // Check for draft bills
        $draftBills = Bill::query()
            ->where('status', Bill::STATUS_DRAFT)
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
            'is_closed' => false,
            'is_locked' => false,
        ]);
    }
}
