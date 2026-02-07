<?php

namespace App\Models\Shared;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\Bill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property DocumentStatus $status
 * @property \Carbon\Carbon $invoice_date
 * @property \Carbon\Carbon $due_date
 * @property int $gross_amount
 * @property int $retention_held
 * @property int $other_deductions
 * @property int $net_amount
 * @property \Carbon\Carbon|null $converted_to_bill_at
 * @property \Carbon\Carbon|null $approved_at
 * @property \Carbon\Carbon|null $rejected_at
 */
class SubcontractorInvoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = DocumentStatus::Submitted->value;

    public const STATUS_APPROVED = DocumentStatus::Approved->value;

    public const STATUS_REJECTED = DocumentStatus::Rejected->value;

    public const STATUS_PAID = DocumentStatus::Paid->value;

    protected $fillable = [
        'invoice_number',
        'subcontractor_work_order_id',
        'subcontractor_id',
        'invoice_date',
        'due_date',
        'gross_amount',
        'retention_held',
        'other_deductions',
        'net_amount',
        'description',
        'status',
        'bill_id',
        'converted_to_bill_at',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SubcontractorInvoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'SCINV-'.now()->format('Ym').'-',
                    'subcontractor_invoices',
                    'invoice_number'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'gross_amount' => 'integer',
            'retention_held' => 'integer',
            'other_deductions' => 'integer',
            'net_amount' => 'integer',
            'converted_to_bill_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SubcontractorWorkOrder, $this>
     */
    public function subcontractorWorkOrder(): BelongsTo
    {
        return $this->belongsTo(SubcontractorWorkOrder::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'subcontractor_id');
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Check if invoice is pending.
     */
    public function isPending(): bool
    {
        return $this->status->value === self::STATUS_PENDING;
    }

    /**
     * Check if invoice is approved.
     */
    public function isApproved(): bool
    {
        return $this->status->value === self::STATUS_APPROVED;
    }

    /**
     * Check if invoice is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status->value === self::STATUS_REJECTED;
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status->value === self::STATUS_PAID;
    }

    /**
     * Check if invoice can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status->value === self::STATUS_PENDING;
    }

    /**
     * Check if invoice can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->status->value === self::STATUS_PENDING;
    }

    /**
     * Check if invoice can be converted to bill.
     */
    public function canBeConvertedToBill(): bool
    {
        return $this->status->value === self::STATUS_APPROVED && $this->bill_id === null;
    }

    /**
     * Check if invoice has been converted to bill.
     */
    public function isConvertedToBill(): bool
    {
        return $this->bill_id !== null;
    }

    /**
     * Calculate net amount.
     */
    public function calculateNetAmount(): int
    {
        return $this->gross_amount - $this->retention_held - $this->other_deductions;
    }

    /**
     * Recalculate amounts.
     */
    public function recalculate(): void
    {
        $this->net_amount = $this->calculateNetAmount();
    }

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_PAID => 'Dibayar',
        ];
    }

    /**
     * Scope by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pending invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope approved invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope invoices not converted to bill.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>
     */
    public function scopeNotConvertedToBill($query)
    {
        return $query->whereNull('bill_id');
    }

    /**
     * Scope for subcontractor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorInvoice>
     */
    public function scopeForSubcontractor($query, int $subcontractorId)
    {
        return $query->where('subcontractor_id', $subcontractorId);
    }
}
