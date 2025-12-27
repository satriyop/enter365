<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_BOTH = 'both';

    protected $fillable = [
        'code',
        'name',
        'type',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'npwp',
        'nik',
        'credit_limit',
        'currency',
        'payment_term_days',
        'early_discount_percent',
        'early_discount_days',
        'is_active',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'notes',
        'last_transaction_date',
        // Subcontractor fields
        'is_subcontractor',
        'subcontractor_services',
        'hourly_rate',
        'daily_rate',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'payment_term_days' => 'integer',
            'early_discount_percent' => 'decimal:2',
            'early_discount_days' => 'integer',
            'is_active' => 'boolean',
            'last_transaction_date' => 'date',
            // Subcontractor fields
            'is_subcontractor' => 'boolean',
            'subcontractor_services' => 'array',
            'hourly_rate' => 'integer',
            'daily_rate' => 'integer',
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<RecurringTemplate, $this>
     */
    public function recurringTemplates(): HasMany
    {
        return $this->hasMany(RecurringTemplate::class);
    }

    /**
     * Get subcontractor work orders.
     *
     * @return HasMany<SubcontractorWorkOrder, $this>
     */
    public function subcontractorWorkOrders(): HasMany
    {
        return $this->hasMany(SubcontractorWorkOrder::class, 'subcontractor_id');
    }

    /**
     * Get subcontractor invoices.
     *
     * @return HasMany<SubcontractorInvoice, $this>
     */
    public function subcontractorInvoices(): HasMany
    {
        return $this->hasMany(SubcontractorInvoice::class, 'subcontractor_id');
    }

    /**
     * Check if contact can be a customer.
     */
    public function isCustomer(): bool
    {
        return in_array($this->type, [self::TYPE_CUSTOMER, self::TYPE_BOTH]);
    }

    /**
     * Check if contact can be a supplier.
     */
    public function isSupplier(): bool
    {
        return in_array($this->type, [self::TYPE_SUPPLIER, self::TYPE_BOTH]);
    }

    /**
     * Get outstanding receivable balance.
     */
    public function getReceivableBalance(): int
    {
        return (int) $this->invoices()
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->sum(DB::raw('total_amount - paid_amount'));
    }

    /**
     * Get outstanding payable balance.
     */
    public function getPayableBalance(): int
    {
        return (int) $this->bills()
            ->whereIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL, Bill::STATUS_OVERDUE])
            ->sum(DB::raw('total_amount - paid_amount'));
    }

    /**
     * Get available credit (credit limit minus outstanding receivables).
     */
    public function getAvailableCredit(): int
    {
        if ($this->credit_limit <= 0) {
            return PHP_INT_MAX; // No limit
        }

        return max(0, $this->credit_limit - $this->getReceivableBalance());
    }

    /**
     * Check if credit limit is exceeded.
     */
    public function isCreditLimitExceeded(): bool
    {
        if ($this->credit_limit <= 0) {
            return false;
        }

        return $this->getReceivableBalance() >= $this->credit_limit;
    }

    /**
     * Check if credit limit warning threshold is reached.
     */
    public function isCreditLimitWarning(): bool
    {
        if ($this->credit_limit <= 0) {
            return false;
        }

        $warnPercent = config('accounting.credit_limit.warn_at_percent', 80);
        $threshold = $this->credit_limit * ($warnPercent / 100);

        return $this->getReceivableBalance() >= $threshold;
    }

    /**
     * Get credit utilization percentage.
     */
    public function getCreditUtilization(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }

        return min(100, ($this->getReceivableBalance() / $this->credit_limit) * 100);
    }

    /**
     * Check if new invoice amount would exceed credit limit.
     */
    public function canCreateInvoice(int $amount = 0): bool
    {
        if (! config('accounting.credit_limit.enabled', true)) {
            return true;
        }

        if ($this->credit_limit <= 0) {
            return true;
        }

        $blockPercent = config('accounting.credit_limit.block_at_percent', 100);
        $threshold = $this->credit_limit * ($blockPercent / 100);

        return ($this->getReceivableBalance() + $amount) <= $threshold;
    }

    /**
     * Update last transaction date.
     */
    public function touchLastTransaction(): void
    {
        $this->update(['last_transaction_date' => now()->toDateString()]);
    }

    /**
     * Get overdue invoices.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    public function getOverdueInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->invoices()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get overdue bills.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Bill>
     */
    public function getOverdueBills(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->bills()
            ->where('status', Bill::STATUS_OVERDUE)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Generate the next contact code.
     */
    public static function generateCode(string $type): string
    {
        $prefix = match ($type) {
            self::TYPE_CUSTOMER => 'C-',
            self::TYPE_SUPPLIER => 'S-',
            self::TYPE_BOTH => 'CS-',
            default => 'X-',
        };

        $lastContact = static::query()
            ->where('code', 'like', $prefix.'%')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastContact) {
            $lastNumber = (int) substr($lastContact->code, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for subcontractors.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Contact>
     */
    public function scopeSubcontractors($query)
    {
        return $query->where('is_subcontractor', true);
    }

    /**
     * Check if contact is a subcontractor.
     */
    public function isSubcontractor(): bool
    {
        return $this->is_subcontractor === true;
    }

    /**
     * Get subcontractor total work orders.
     */
    public function getSubcontractorTotalWorkOrders(): int
    {
        return $this->subcontractorWorkOrders()->count();
    }

    /**
     * Get subcontractor active work orders.
     */
    public function getSubcontractorActiveWorkOrders(): int
    {
        return $this->subcontractorWorkOrders()
            ->whereIn('status', [
                SubcontractorWorkOrder::STATUS_ASSIGNED,
                SubcontractorWorkOrder::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    /**
     * Get subcontractor completed work orders.
     */
    public function getSubcontractorCompletedWorkOrders(): int
    {
        return $this->subcontractorWorkOrders()
            ->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)
            ->count();
    }

    /**
     * Get subcontractor total revenue.
     */
    public function getSubcontractorTotalRevenue(): int
    {
        return (int) $this->subcontractorWorkOrders()
            ->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)
            ->sum('actual_amount');
    }
}
