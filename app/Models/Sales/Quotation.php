<?php

namespace App\Models\Sales;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Domain\Sales\Quotations\QuotationStateMachine;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Projects\Project;
use App\Models\Shared\Attachment;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\Filterable;
use App\Traits\HasDocumentDiscount;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $quotation_number
 * @property int $revision
 * @property int $contact_id
 * @property int|null $project_id
 * @property Carbon $quotation_date
 * @property Carbon $valid_until
 * @property string|null $reference
 * @property string|null $subject
 * @property string|null $quotation_type
 * @property int|null $variant_group_id
 * @property int|null $selected_variant_id
 * @property int|null $source_bom_id
 * @property DocumentStatus $status
 * @property Carbon|null $next_follow_up_at
 * @property Carbon|null $last_contacted_at
 * @property int|null $assigned_to
 * @property int $follow_up_count
 * @property QuotationPriority|null $priority
 * @property string $currency
 * @property string $exchange_rate
 * @property int $subtotal
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property int $discount_amount
 * @property string $tax_rate
 * @property int $tax_amount
 * @property int $total
 * @property int $base_currency_total
 * @property string|null $notes
 * @property string|null $terms_conditions
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $sent_at
 * @property int|null $sent_by
 * @property string|null $sent_to_email
 * @property string|null $sent_via
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property QuotationOutcome|null $outcome
 * @property string|null $won_reason
 * @property string|null $lost_reason
 * @property string|null $lost_to_competitor
 * @property string|null $outcome_notes
 * @property Carbon|null $outcome_at
 * @property int|null $converted_to_invoice_id
 * @property Carbon|null $converted_at
 * @property int|null $original_quotation_id
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Quotation extends Model
{
    use Auditable, Filterable, HasDocumentDiscount, HasFactory, HasStatusHistory, SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'revision',
        'contact_id',
        'project_id',
        'quotation_date',
        'valid_until',
        'reference',
        'subject',
        'quotation_type',
        'variant_group_id',
        'selected_variant_id',
        'source_bom_id',
        'status',
        // Follow-up fields
        'next_follow_up_at',
        'last_contacted_at',
        'assigned_to',
        'follow_up_count',
        'priority',
        // Financial fields
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'base_currency_total',
        'notes',
        'terms_conditions',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        // Sent tracking fields
        'sent_at',
        'sent_by',
        'sent_to_email',
        'sent_via',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        // Outcome fields
        'outcome',
        'won_reason',
        'lost_reason',
        'lost_to_competitor',
        'outcome_notes',
        'outcome_at',
        'converted_to_invoice_id',
        'converted_at',
        'original_quotation_id',
        'created_by',
        // Cancellation fields
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'revision' => 'integer',
            'exchange_rate' => 'decimal:4',
            'subtotal' => 'integer',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'base_currency_total' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
            // Follow-up casts
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'follow_up_count' => 'integer',
            // Outcome casts
            'outcome_at' => 'datetime',
            // Cancellation casts
            'cancelled_at' => 'datetime',
            'status' => DocumentStatus::class,
            'priority' => QuotationPriority::class,
            'quotation_type' => QuotationType::class,
            'outcome' => QuotationOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Check if quotation has been sent to customer.
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function originalQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'original_quotation_id');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'original_quotation_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<QuotationActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(QuotationActivity::class)->orderByDesc('activity_at');
    }

    /**
     * @return BelongsTo<BomVariantGroup, $this>
     */
    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(BomVariantGroup::class, 'variant_group_id');
    }

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function selectedVariant(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'selected_variant_id');
    }

    /**
     * Source BOM when quotation is created from a BOM.
     *
     * @return BelongsTo<Bom, $this>
     */
    public function sourceBom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'source_bom_id');
    }

    /**
     * @return HasMany<QuotationVariantOption, $this>
     */
    public function variantOptions(): HasMany
    {
        return $this->hasMany(QuotationVariantOption::class)->orderBy('sort_order');
    }

    /**
     * Check if this is a multi-option quotation.
     */
    public function isMultiOption(): bool
    {
        return $this->quotation_type === QuotationType::MultiOption;
    }

    /**
     * Check if customer has selected a variant.
     */
    public function hasSelectedVariant(): bool
    {
        return $this->selected_variant_id !== null;
    }

    /**
     * Get the recommended variant option.
     */
    public function getRecommendedOption(): ?QuotationVariantOption
    {
        return $this->variantOptions->firstWhere('is_recommended', true);
    }

    /**
     * Get variant comparison summary for customer display.
     *
     * @return array{options: array<int, array<string, mixed>>, price_range: array{min: int, max: int, difference: int}}|null
     */
    public function getVariantComparison(): ?array
    {
        if (! $this->isMultiOption() || $this->variantOptions->isEmpty()) {
            return null;
        }

        $options = $this->variantOptions->map(fn (QuotationVariantOption $option) => [
            'id' => $option->id,
            'bom_id' => $option->bom_id,
            'display_name' => $option->display_name,
            'tagline' => $option->tagline,
            'is_recommended' => $option->is_recommended,
            'selling_price' => $option->selling_price,
            'features' => $option->features,
            'specifications' => $option->specifications,
            'warranty_terms' => $option->warranty_terms,
        ])->toArray();

        $prices = $this->variantOptions->pluck('selling_price');

        return [
            'options' => $options,
            'price_range' => [
                'min' => $prices->min(),
                'max' => $prices->max(),
                'difference' => $prices->max() - $prices->min(),
            ],
        ];
    }

    /**
     * Scope for draft quotations.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Draft);
    }

    /**
     * Scope for submitted quotations.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Submitted);
    }

    /**
     * Scope for approved quotations.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Approved);
    }

    /**
     * Scope for pending quotations (submitted but not decided).
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Submitted);
    }

    /**
     * Scope for active quotations (not expired/converted/rejected/cancelled).
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            DocumentStatus::Expired,
            DocumentStatus::Converted,
            DocumentStatus::Rejected,
            DocumentStatus::Cancelled,
        ]);
    }

    /**
     * Scope for expired quotations.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Expired);
    }

    /**
     * Get the state machine instance for this quotation.
     *
     * Note: For mutations (transitionTo), use QuotationDomainFactory
     * to get a state machine with proper event dispatch.
     */
    public function stateMachine(): QuotationStateMachine
    {
        return QuotationStateMachine::fromQuotation($this);
    }

    /**
     * Check if quotation is editable.
     */
    public function isEditable(): bool
    {
        return $this->stateMachine()->canEdit();
    }

    /**
     * Check if quotation can be submitted.
     */
    public function canSubmit(): bool
    {
        return $this->stateMachine()->canSubmit();
    }

    /**
     * Check if quotation can be approved.
     */
    public function canApprove(): bool
    {
        return $this->stateMachine()->canApprove();
    }

    /**
     * Check if quotation can be rejected.
     */
    public function canReject(): bool
    {
        return $this->stateMachine()->canReject();
    }

    /**
     * Check if quotation can be converted to invoice.
     */
    public function canConvert(): bool
    {
        return $this->stateMachine()->canConvert();
    }

    /**
     * Check if quotation can be revised.
     */
    public function canRevise(): bool
    {
        return $this->stateMachine()->canRevise();
    }

    /**
     * Check if quotation can be cancelled.
     */
    public function canCancel(): bool
    {
        return $this->stateMachine()->canCancel();
    }

    /**
     * Check if quotation is expired.
     */
    public function isExpired(): bool
    {
        return $this->stateMachine()->isExpired();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiry(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) now()->diffInDays($this->valid_until);
    }

    /**
     * Get status label in Indonesian.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get default terms and conditions from config.
     */
    public static function getDefaultTermsConditions(string $locale = 'id'): string
    {
        $template = config("accounting.quotation.terms_conditions.{$locale}", '');
        $validityDays = config('accounting.quotation.default_validity_days', 30);

        return str_replace('{validity_days}', (string) $validityDays, $template);
    }

    /**
     * Get the full quotation number with revision suffix.
     *
     * Format: QUO-YYYYMM-XXXX or QUO-YYYYMM-XXXX-R{n} for revisions.
     */
    public function getFullNumber(): string
    {
        if ($this->revision > 0) {
            return "{$this->quotation_number}-R{$this->revision}";
        }

        return $this->quotation_number;
    }

    // ========================================
    // Follow-Up Scopes
    // ========================================

    /**
     * Scope for quotations needing follow-up today or earlier.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->active();
    }

    /**
     * Scope for quotations with overdue follow-up.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now()->startOfDay())
            ->active();
    }

    /**
     * Scope for quotations assigned to a specific user.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for quotations with high priority.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [QuotationPriority::High->value, QuotationPriority::Urgent->value]);
    }

    /**
     * Scope for quotations that are won.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeWon(Builder $query): Builder
    {
        return $query->where('outcome', QuotationOutcome::Won->value);
    }

    /**
     * Scope for quotations that are lost.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeLost(Builder $query): Builder
    {
        return $query->where('outcome', QuotationOutcome::Lost->value);
    }

    // ========================================
    // Read-Only Accessors
    // ========================================

    /**
     * Check if quotation needs follow-up (for API/display).
     */
    public function needsFollowUp(): bool
    {
        if ($this->next_follow_up_at === null) {
            return false;
        }

        return $this->next_follow_up_at->isPast() && $this->outcome === null;
    }

    /**
     * Get days since last contact (for API/display).
     */
    public function getDaysSinceLastContact(): ?int
    {
        if ($this->last_contacted_at === null) {
            return null;
        }

        return (int) $this->last_contacted_at->diffInDays(now());
    }

    /**
     * Get outcome label in Indonesian.
     */
    public function getOutcomeLabel(): ?string
    {
        if ($this->outcome === null) {
            return null;
        }

        return $this->outcome->label();
    }

    /**
     * Get priority label in Indonesian.
     */
    public function getPriorityLabel(): string
    {
        if ($this->priority === null) {
            return 'Normal';
        }

        return $this->priority->label();
    }
}
