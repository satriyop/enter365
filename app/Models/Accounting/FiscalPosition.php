<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\Tax\TaxRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPosition extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\FiscalPositionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FiscalPositionTaxMap, $this>
     */
    public function taxMaps(): HasMany
    {
        return $this->hasMany(FiscalPositionTaxMap::class);
    }

    /**
     * @return HasMany<FiscalPositionAccountMap, $this>
     */
    public function accountMaps(): HasMany
    {
        return $this->hasMany(FiscalPositionAccountMap::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Map product/line tax records through this position (Odoo one-hop).
     *
     * Unmapped taxes stay. A null dest tax is exemption (dropped).
     *
     * @param  list<int>  $taxRecordIds
     * @return list<int>
     */
    public function mapTaxRecordIds(array $taxRecordIds): array
    {
        if ($taxRecordIds === []) {
            return [];
        }

        $maps = $this->taxMaps->keyBy('source_tax_record_id');
        $mapped = [];

        foreach ($taxRecordIds as $sourceId) {
            $sourceId = (int) $sourceId;
            $map = $maps->get($sourceId);
            if ($map === null) {
                $mapped[] = $sourceId;

                continue;
            }

            if ($map->dest_tax_record_id !== null) {
                $mapped[] = (int) $map->dest_tax_record_id;
            }
        }

        return array_values(array_unique($mapped));
    }

    public function mapAccountId(?int $accountId): ?int
    {
        if ($accountId === null) {
            return null;
        }

        $map = $this->accountMaps->firstWhere('source_account_id', $accountId);

        return $map === null ? $accountId : (int) $map->dest_account_id;
    }

    /**
     * @param  list<int>  $taxRecordIds
     */
    public function mappedTaxRate(array $taxRecordIds): float
    {
        $mappedIds = $this->mapTaxRecordIds($taxRecordIds);
        if ($mappedIds === []) {
            return 0.0;
        }

        return (float) TaxRecord::query()
            ->whereIn('id', $mappedIds)
            ->get()
            ->sum(fn (TaxRecord $tax): float => (float) $tax->rate);
    }
}
