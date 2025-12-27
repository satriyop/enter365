<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCost extends Model
{
    use HasFactory;

    public const TYPE_MATERIAL = 'material';

    public const TYPE_LABOR = 'labor';

    public const TYPE_SUBCONTRACTOR = 'subcontractor';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_OVERHEAD = 'overhead';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'project_id',
        'cost_type',
        'description',
        'cost_date',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'bill_id',
        'product_id',
        'vendor_name',
        'is_billable',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'is_billable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate total cost.
     */
    public function calculateTotalCost(): void
    {
        $this->total_cost = (int) round((float) $this->quantity * $this->unit_cost);
    }

    /**
     * Get available cost types.
     *
     * @return array<string, string>
     */
    public static function getCostTypes(): array
    {
        return [
            self::TYPE_MATERIAL => 'Material',
            self::TYPE_LABOR => 'Tenaga Kerja',
            self::TYPE_SUBCONTRACTOR => 'Subkontraktor',
            self::TYPE_EQUIPMENT => 'Peralatan',
            self::TYPE_OVERHEAD => 'Overhead',
            self::TYPE_OTHER => 'Lainnya',
        ];
    }
}
