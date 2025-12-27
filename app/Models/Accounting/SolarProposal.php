<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarProposal extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    // Roof type constants
    public const ROOF_TYPE_FLAT = 'flat';

    public const ROOF_TYPE_SLOPED = 'sloped';

    public const ROOF_TYPE_CARPORT = 'carport';

    public const ROOF_TYPE_GROUND = 'ground';

    // Roof orientation constants
    public const ORIENTATION_NORTH = 'north';

    public const ORIENTATION_SOUTH = 'south';

    public const ORIENTATION_EAST = 'east';

    public const ORIENTATION_WEST = 'west';

    public const ORIENTATION_NORTHEAST = 'northeast';

    public const ORIENTATION_NORTHWEST = 'northwest';

    public const ORIENTATION_SOUTHEAST = 'southeast';

    public const ORIENTATION_SOUTHWEST = 'southwest';

    protected $fillable = [
        'proposal_number',
        'contact_id',
        'status',
        // Site Information
        'site_name',
        'site_address',
        'province',
        'city',
        'latitude',
        'longitude',
        'roof_area_m2',
        'roof_type',
        'roof_orientation',
        'roof_tilt_degrees',
        'shading_percentage',
        // Electricity Profile
        'monthly_consumption_kwh',
        'pln_tariff_category',
        'electricity_rate',
        'tariff_escalation_percent',
        // Solar Data
        'peak_sun_hours',
        'solar_irradiance',
        'performance_ratio',
        // System Selection
        'variant_group_id',
        'selected_bom_id',
        'system_capacity_kwp',
        'annual_production_kwh',
        // Calculated Results (JSON)
        'financial_analysis',
        'environmental_impact',
        // Proposal Settings
        'sections_config',
        'custom_content',
        'valid_until',
        'notes',
        // Metadata
        'created_by',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'converted_quotation_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'roof_area_m2' => 'decimal:2',
            'roof_tilt_degrees' => 'decimal:2',
            'shading_percentage' => 'decimal:2',
            'monthly_consumption_kwh' => 'decimal:2',
            'electricity_rate' => 'integer',
            'tariff_escalation_percent' => 'decimal:2',
            'peak_sun_hours' => 'decimal:2',
            'solar_irradiance' => 'decimal:2',
            'performance_ratio' => 'decimal:2',
            'system_capacity_kwp' => 'decimal:2',
            'annual_production_kwh' => 'decimal:2',
            'financial_analysis' => 'array',
            'environmental_impact' => 'array',
            'sections_config' => 'array',
            'custom_content' => 'array',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    // ========================================
    // Relationships
    // ========================================

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
    public function selectedBom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'selected_bom_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function convertedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'converted_quotation_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ========================================
    // Scopes
    // ========================================

    /**
     * Scope for draft proposals.
     *
     * @param  Builder<SolarProposal>  $query
     * @return Builder<SolarProposal>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for sent proposals.
     *
     * @param  Builder<SolarProposal>  $query
     * @return Builder<SolarProposal>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for accepted proposals.
     *
     * @param  Builder<SolarProposal>  $query
     * @return Builder<SolarProposal>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for active proposals (not expired/rejected).
     *
     * @param  Builder<SolarProposal>  $query
     * @return Builder<SolarProposal>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            self::STATUS_EXPIRED,
            self::STATUS_REJECTED,
        ]);
    }

    // ========================================
    // Status Checks
    // ========================================

    /**
     * Check if proposal is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if proposal can be sent.
     */
    public function canSend(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->variant_group_id !== null
            && $this->financial_analysis !== null;
    }

    /**
     * Check if proposal can be accepted.
     */
    public function canAccept(): bool
    {
        return $this->status === self::STATUS_SENT
            && ! $this->isExpired();
    }

    /**
     * Check if proposal can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if proposal can be converted to quotation.
     */
    public function canConvert(): bool
    {
        return $this->status === self::STATUS_ACCEPTED
            && $this->converted_quotation_id === null
            && $this->selected_bom_id !== null;
    }

    /**
     * Check if proposal is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->valid_until === null) {
            return false;
        }

        return $this->valid_until->isPast();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiry(): ?int
    {
        if ($this->valid_until === null) {
            return null;
        }

        if ($this->valid_until->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($this->valid_until);
    }

    // ========================================
    // Status Labels
    // ========================================

    /**
     * Get status label in Indonesian.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_ACCEPTED => 'Diterima',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            default => $this->status,
        };
    }

    /**
     * Get roof type label.
     */
    public function getRoofTypeLabel(): string
    {
        return match ($this->roof_type) {
            self::ROOF_TYPE_FLAT => 'Atap Datar',
            self::ROOF_TYPE_SLOPED => 'Atap Miring',
            self::ROOF_TYPE_CARPORT => 'Carport',
            self::ROOF_TYPE_GROUND => 'Ground Mount',
            default => $this->roof_type ?? '-',
        };
    }

    /**
     * Get roof orientation label.
     */
    public function getOrientationLabel(): string
    {
        return match ($this->roof_orientation) {
            self::ORIENTATION_NORTH => 'Utara',
            self::ORIENTATION_SOUTH => 'Selatan',
            self::ORIENTATION_EAST => 'Timur',
            self::ORIENTATION_WEST => 'Barat',
            self::ORIENTATION_NORTHEAST => 'Timur Laut',
            self::ORIENTATION_NORTHWEST => 'Barat Laut',
            self::ORIENTATION_SOUTHEAST => 'Tenggara',
            self::ORIENTATION_SOUTHWEST => 'Barat Daya',
            default => $this->roof_orientation ?? '-',
        };
    }

    // ========================================
    // Financial Analysis Helpers
    // ========================================

    /**
     * Get payback period in years.
     */
    public function getPaybackPeriod(): ?float
    {
        return $this->financial_analysis['payback_years'] ?? null;
    }

    /**
     * Get 25-year ROI percentage.
     */
    public function getRoi(): ?float
    {
        return $this->financial_analysis['roi_percent'] ?? null;
    }

    /**
     * Get NPV at discount rate.
     */
    public function getNpv(): ?int
    {
        return $this->financial_analysis['npv'] ?? null;
    }

    /**
     * Get IRR percentage.
     */
    public function getIrr(): ?float
    {
        return $this->financial_analysis['irr_percent'] ?? null;
    }

    /**
     * Get first year savings.
     */
    public function getFirstYearSavings(): ?int
    {
        $yearlyProjections = $this->financial_analysis['yearly_projections'] ?? null;
        if ($yearlyProjections && isset($yearlyProjections[0])) {
            return $yearlyProjections[0]['savings'] ?? null;
        }

        return null;
    }

    /**
     * Get total 25-year savings.
     */
    public function getTotalLifetimeSavings(): ?int
    {
        return $this->financial_analysis['total_lifetime_savings'] ?? null;
    }

    // ========================================
    // Environmental Impact Helpers
    // ========================================

    /**
     * Get CO2 offset in tons per year.
     */
    public function getCo2OffsetTons(): ?float
    {
        return $this->environmental_impact['co2_offset_tons_per_year'] ?? null;
    }

    /**
     * Get trees equivalent.
     */
    public function getTreesEquivalent(): ?int
    {
        return $this->environmental_impact['trees_equivalent'] ?? null;
    }

    /**
     * Get cars off road equivalent.
     */
    public function getCarsEquivalent(): ?float
    {
        return $this->environmental_impact['cars_equivalent'] ?? null;
    }

    // ========================================
    // Number Generation
    // ========================================

    /**
     * Generate the next proposal number.
     */
    public static function generateProposalNumber(): string
    {
        $prefix = 'SPR-'.now()->format('Ym').'-';
        $lastProposal = static::query()
            ->where('proposal_number', 'like', $prefix.'%')
            ->orderBy('proposal_number', 'desc')
            ->first();

        if ($lastProposal) {
            $lastNumber = (int) substr($lastProposal->proposal_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // ========================================
    // System Sizing Helpers
    // ========================================

    /**
     * Get the system cost from the selected BOM.
     */
    public function getSystemCost(): ?int
    {
        if ($this->selectedBom) {
            return $this->selectedBom->total_cost;
        }

        return null;
    }

    /**
     * Get monthly production estimate.
     */
    public function getMonthlyProduction(): ?float
    {
        if ($this->annual_production_kwh === null) {
            return null;
        }

        return round((float) $this->annual_production_kwh / 12, 2);
    }

    /**
     * Get solar offset percentage (how much of consumption is covered).
     */
    public function getSolarOffsetPercent(): ?float
    {
        if ($this->monthly_consumption_kwh === null || $this->monthly_consumption_kwh == 0) {
            return null;
        }

        $monthlyProduction = $this->getMonthlyProduction();
        if ($monthlyProduction === null) {
            return null;
        }

        return round(($monthlyProduction / (float) $this->monthly_consumption_kwh) * 100, 1);
    }
}
