<?php

namespace App\Models\Sales;

use App\Domain\Sales\Quotations\FollowUpManager;
use App\Domain\Sales\Quotations\OutcomeManager;
use App\Domain\Sales\Quotations\QuotationStateMachine;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Shared\Attachment;
use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    // Win/Loss outcome constants
    public const OUTCOME_WON = 'won';

    public const OUTCOME_LOST = 'lost';

    public const OUTCOME_CANCELLED = 'cancelled';

    // Won reasons (in Indonesian)
    public const WON_REASONS = [
        'harga_kompetitif' => 'Harga Kompetitif',
        'kualitas_produk' => 'Kualitas Produk',
        'layanan_baik' => 'Layanan yang Baik',
        'waktu_pengiriman' => 'Waktu Pengiriman Cepat',
        'hubungan_baik' => 'Hubungan Baik dengan Pelanggan',
        'spesifikasi_sesuai' => 'Spesifikasi Sesuai Kebutuhan',
        'rekomendasi' => 'Rekomendasi dari Pelanggan Lain',
        'lainnya' => 'Lainnya',
    ];

    // Lost reasons (in Indonesian)
    public const LOST_REASONS = [
        'harga_tinggi' => 'Harga Terlalu Tinggi',
        'kalah_kompetitor' => 'Kalah dari Kompetitor',
        'spesifikasi_tidak_sesuai' => 'Spesifikasi Tidak Sesuai',
        'waktu_pengiriman_lama' => 'Waktu Pengiriman Terlalu Lama',
        'proyek_dibatalkan' => 'Proyek Dibatalkan',
        'tidak_ada_budget' => 'Tidak Ada Budget',
        'tidak_ada_respon' => 'Tidak Ada Respon dari Pelanggan',
        'lainnya' => 'Lainnya',
    ];

    // Priority levels
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    // Quotation types
    public const TYPE_SINGLE = 'single';

    public const TYPE_MULTI_OPTION = 'multi_option';

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
        'total',
        'base_currency_total',
        'notes',
        'terms_conditions',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
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
            'total' => 'integer',
            'base_currency_total' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
            // Follow-up casts
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'follow_up_count' => 'integer',
            // Outcome casts
            'outcome_at' => 'datetime',
            'status' => DocumentStatus::class,
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
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
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
        return $this->quotation_type === self::TYPE_MULTI_OPTION;
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
     * Scope for active quotations (not expired/converted/rejected).
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
     */
    public function stateMachine(): QuotationStateMachine
    {
        return QuotationStateMachine::fromQuotation(
            $this->status,
            $this->items()->exists(),
            $this->valid_until,
            $this->converted_to_invoice_id
        );
    }

    /**
     * Get the outcome manager instance for this quotation.
     */
    public function outcomeManager(): OutcomeManager
    {
        return new OutcomeManager;
    }

    /**
     * Get the follow-up manager instance for this quotation.
     */
    public function followUpManager(): FollowUpManager
    {
        return new FollowUpManager;
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
     * Get the full quotation number with revision suffix.
     */
    public function getFullNumber(): string
    {
        if ($this->revision > 0) {
            return "{$this->quotation_number}-R{$this->revision}";
        }

        return $this->quotation_number;
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
     * Generate the next quotation number.
     */
    public static function generateQuotationNumber(): string
    {
        $prefix = 'QUO-'.now()->format('Ym').'-';
        $lastQuotation = static::query()
            ->where('quotation_number', 'like', $prefix.'%')
            ->orderBy('quotation_number', 'desc')
            ->first();

        if ($lastQuotation) {
            $lastNumber = (int) substr($lastQuotation->quotation_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next revision number for this quotation.
     */
    public function getNextRevisionNumber(): int
    {
        // If this is already a revision, find the original
        $originalId = $this->original_quotation_id ?? $this->id;

        $maxRevision = static::query()
            ->where(function ($query) use ($originalId) {
                $query->where('id', $originalId)
                    ->orWhere('original_quotation_id', $originalId);
            })
            ->max('revision');

        return ($maxRevision ?? 0) + 1;
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
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Scope for quotations that are won.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeWon(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_WON);
    }

    /**
     * Scope for quotations that are lost.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeLost(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_LOST);
    }

    // ========================================
    // Follow-Up Methods
    // ========================================

    /**
     * Schedule next follow-up.
     */
    public function scheduleFollowUp(int $daysFromNow = 3): void
    {
        $this->followUpManager()->scheduleFollowUp($this, $daysFromNow);
    }

    /**
     * Record a contact activity and update last_contacted_at.
     */
    public function recordContact(): void
    {
        $this->followUpManager()->recordContact($this);
    }

    /**
     * Calculate auto follow-up date based on quotation stage.
     */
    public function calculateAutoFollowUpDate(): ?\DateTimeInterface
    {
        return $this->followUpManager()->calculateAutoFollowUpDate($this);
    }

    /**
     * Check if quotation needs follow-up.
     */
    public function needsFollowUp(): bool
    {
        return $this->followUpManager()->needsFollowUp($this);
    }

    /**
     * Get days since last contact.
     */
    public function getDaysSinceLastContact(): ?int
    {
        return $this->followUpManager()->getDaysSinceLastContact($this);
    }

    // ========================================
    // Outcome Methods
    // ========================================

    /**
     * Mark quotation as won.
     *
     * @param  array{won_reason?: string, outcome_notes?: string}  $data
     */
    public function markAsWon(array $data = []): void
    {
        $this->outcomeManager()->markAsWon($this, $data);
    }

    /**
     * Mark quotation as lost.
     *
     * @param  array{lost_reason?: string, lost_to_competitor?: string, outcome_notes?: string}  $data
     */
    public function markAsLost(array $data = []): void
    {
        $this->outcomeManager()->markAsLost($this, $data);
    }

    /**
     * Get outcome label in Indonesian.
     */
    public function getOutcomeLabel(): ?string
    {
        return $this->outcomeManager()->getOutcomeLabel($this->outcome);
    }

    /**
     * Get priority label in Indonesian.
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'Rendah',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Tinggi',
            self::PRIORITY_URGENT => 'Mendesak',
            default => $this->priority ?? 'Normal',
        };
    }
}
