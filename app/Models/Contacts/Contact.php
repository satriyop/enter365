<?php

namespace App\Models\Contacts;

use App\Enums\DocumentStatus;
use App\Enums\PphCategory;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Models\Shared\SubcontractorInvoice;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property-read int|null $active_work_orders_count
 * @property-read int|null $completed_work_orders_count
 */
class Contact extends Model
{
    use Filterable, HasFactory, SoftDeletes;

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
        // PPh (Withholding Tax) fields
        'is_pph_subject',
        'pph_category',
        'pph_rate',
        'is_foreign_entity',
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
            // PPh fields
            'is_pph_subject' => 'boolean',
            'pph_category' => PphCategory::class,
            'pph_rate' => 'decimal:2',
            'is_foreign_entity' => 'boolean',
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
     * @return HasMany<\App\Models\Shared\RecurringTemplate, $this>
     */
    public function recurringTemplates(): HasMany
    {
        return $this->hasMany(\App\Models\Shared\RecurringTemplate::class);
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
            ->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Partial, DocumentStatus::Overdue])
            ->sum(DB::raw('total_amount - paid_amount'));
    }

    /**
     * Get outstanding payable balance.
     */
    public function getPayableBalance(): int
    {
        return (int) $this->bills()
            ->whereIn('status', [DocumentStatus::Received, DocumentStatus::Partial, DocumentStatus::Overdue])
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
            ->where('status', DocumentStatus::Overdue)
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
            ->where('status', DocumentStatus::Overdue)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Check if this contact is subject to PPh withholding.
     */
    public function isPphSubject(): bool
    {
        return $this->is_pph_subject === true;
    }

    /**
     * Get the effective PPh rate, applying 200% surcharge if no NPWP.
     */
    public function getEffectivePphRate(): float
    {
        if (! $this->isPphSubject() || ! $this->pph_category) {
            return 0;
        }

        $baseRate = $this->pph_rate ?? $this->pph_category->effectiveRate();
        $surchargeMultiplier = 1.0;

        // No-NPWP surcharge (PPh 23 only, not PPh 4(2) final tax, not PPh 26)
        if (empty($this->npwp) && ! $this->pph_category->isFinal() && $this->pph_category !== PphCategory::Pph26) {
            $surchargeMultiplier = (float) config('accounting.pph.no_npwp_surcharge_multiplier', 2.0);
        }

        return $baseRate * $surchargeMultiplier;
    }

    /**
     * Get the PPh payable account code for this contact's category.
     */
    public function getPphAccountCode(): ?string
    {
        return $this->pph_category?->accountCode();
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
                DocumentStatus::Assigned,
                DocumentStatus::InProgress,
            ])
            ->count();
    }

    /**
     * Get subcontractor completed work orders.
     */
    public function getSubcontractorCompletedWorkOrders(): int
    {
        return $this->subcontractorWorkOrders()
            ->where('status', DocumentStatus::Completed)
            ->count();
    }

    /**
     * Get subcontractor total revenue.
     */
    public function getSubcontractorTotalRevenue(): int
    {
        return (int) $this->subcontractorWorkOrders()
            ->where('status', DocumentStatus::Completed)
            ->sum('actual_amount');
    }
}
