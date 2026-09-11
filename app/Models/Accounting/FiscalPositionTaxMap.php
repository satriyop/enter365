<?php

namespace App\Models\Accounting;

use App\Models\Tax\TaxRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalPositionTaxMap extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\FiscalPositionTaxMapFactory> */
    use HasFactory;

    protected $fillable = [
        'fiscal_position_id',
        'source_tax_record_id',
        'dest_tax_record_id',
    ];

    /**
     * @return BelongsTo<FiscalPosition, $this>
     */
    public function fiscalPosition(): BelongsTo
    {
        return $this->belongsTo(FiscalPosition::class);
    }

    /**
     * @return BelongsTo<TaxRecord, $this>
     */
    public function sourceTax(): BelongsTo
    {
        return $this->belongsTo(TaxRecord::class, 'source_tax_record_id');
    }

    /**
     * @return BelongsTo<TaxRecord, $this>
     */
    public function destTax(): BelongsTo
    {
        return $this->belongsTo(TaxRecord::class, 'dest_tax_record_id');
    }
}
