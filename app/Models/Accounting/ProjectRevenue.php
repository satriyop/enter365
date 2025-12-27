<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRevenue extends Model
{
    use HasFactory;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_DOWN_PAYMENT = 'down_payment';

    public const TYPE_MILESTONE = 'milestone';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'project_id',
        'revenue_type',
        'description',
        'revenue_date',
        'amount',
        'invoice_id',
        'down_payment_id',
        'milestone_name',
        'milestone_percentage',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'revenue_date' => 'date',
            'amount' => 'integer',
            'milestone_percentage' => 'decimal:2',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<DownPayment, $this>
     */
    public function downPayment(): BelongsTo
    {
        return $this->belongsTo(DownPayment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get available revenue types.
     *
     * @return array<string, string>
     */
    public static function getRevenueTypes(): array
    {
        return [
            self::TYPE_INVOICE => 'Invoice',
            self::TYPE_DOWN_PAYMENT => 'Uang Muka',
            self::TYPE_MILESTONE => 'Milestone',
            self::TYPE_OTHER => 'Lainnya',
        ];
    }
}
