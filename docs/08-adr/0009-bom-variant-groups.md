---
adr: "0009"
title: "BOM Variant Groups for Multi-Brand Alternatives"
status: accepted
date: 2024-11-15
deciders: [Product Team, Domain Expert]
tags: [domain, manufacturing, killer-feature]
related_adrs: [0015, 0016, 0017]
related_modules: [manufacturing, sales]
impact: high
---

# ADR-0009: BOM Variant Groups for Multi-Brand Alternatives

## AI Agent Quick Reference

**Use this ADR when:**
- Understanding the BOM variant system
- Implementing multi-brand quotations
- Working with component alternatives (ABB/Schneider/Siemens)
- Building the electrical panel manufacturing feature

**Key takeaway:** BOM Variant Groups allow creating Budget/Standard/Premium versions of the same product using different component brands, enabling competitive multi-option quotations.

---

## Context

Enter365 targets **electrical panel manufacturers** who face a unique challenge: customers request quotations with different brand options to compare prices and quality.

### Industry Pain Point

When a customer requests a quotation for an electrical panel:
1. They want to compare **ABB** (premium) vs **Schneider** (standard) vs **Siemens** (budget)
2. Each brand has equivalent components (MCBs, contactors, etc.)
3. Prices vary significantly by brand
4. Currently done manually in spreadsheets - error-prone and slow

### Business Opportunity

A system that can:
- Define component equivalencies across brands
- Generate multiple BOM variants automatically
- Produce side-by-side quotation comparisons
- Let customers choose their preferred option

This is a **killer feature** for the target market.

---

## Decision Drivers

1. **Market Differentiation** - Unique feature competitors lack
2. **Time Savings** - Hours of spreadsheet work reduced to minutes
3. **Error Reduction** - Automated calculations, no manual errors
4. **Customer Experience** - Professional multi-option quotations
5. **Win Rate** - Better proposals win more projects

---

## Considered Options

### Option 1: BOM Variant Groups (Chosen)

**Description:** Create a "variant group" that links multiple BOMs as alternatives for the same product.

```
BOM Variant Group: "Panel MDP 100A - 3 Options"
├── BOM: Budget (Siemens components)     → Rp 45.000.000
├── BOM: Standard (Schneider components) → Rp 52.000.000
└── BOM: Premium (ABB components)        → Rp 68.000.000
```

**Pros:**
- Clear relationship between variants
- Each BOM is a complete, valid recipe
- Easy to add/remove variants
- Supports different component counts per variant

**Cons:**
- Requires creating multiple BOMs
- Some data duplication

### Option 2: Single BOM with Alternative Components

**Description:** One BOM with multiple component choices per line.

**Pros:**
- Less data duplication
- Single source of truth

**Cons:**
- Complex data model
- Hard to manage when variants have different items
- Confusing for users

### Option 3: Component Cross-Reference Only

**Description:** Just map equivalent components, generate BOMs on-the-fly.

**Pros:**
- Minimal data duplication

**Cons:**
- No flexibility for variant-specific adjustments
- Complex generation logic
- Hard to audit/verify

---

## Decision

**Chosen option:** "BOM Variant Groups"

A variant group links multiple BOMs that represent different versions (Budget/Standard/Premium) of the same product. Each BOM is a complete, independent bill of materials.

---

## Rationale

### Why Variant Groups:

1. **Flexibility**
   - Each BOM can have different line items
   - Premium version might include extra features
   - Easy to add/remove variants

2. **Simplicity**
   - Each BOM is a complete, valid recipe
   - No complex conditional logic
   - Standard BOM operations work on each variant

3. **Auditability**
   - Clear trail of what each variant contains
   - Can compare variants side-by-side
   - Cost breakdowns per variant

4. **User Experience**
   - Create one BOM, duplicate for variants
   - Swap components using cross-reference
   - See cost comparison immediately

---

## Consequences

### Positive

- Killer feature for electrical panel market
- Multi-option quotations (Budget/Standard/Premium)
- Reduced quotation preparation time
- Higher win rate with better proposals
- Clear data model

### Negative

- Multiple BOMs for one product
- Need to maintain consistency across variants
- More storage for variant data

### Neutral

- Requires component cross-reference system (ADR-0010)
- Need training for variant workflow

---

## Implementation Notes

### Data Model

```
┌─────────────────────────────────────┐
│         BomVariantGroup             │
├─────────────────────────────────────┤
│ id                                  │
│ name: "Panel MDP 100A"              │
│ description                         │
│ product_id (finished product)       │
│ status: active                      │
└──────────────┬──────────────────────┘
               │ has_many
               ▼
┌─────────────────────────────────────┐
│              Bom                    │
├─────────────────────────────────────┤
│ id                                  │
│ variant_group_id ←───────────────── │
│ variant_name: "Budget" | "Standard" │
│ variant_sort_order: 1, 2, 3         │
│ product_id (same as group)          │
│ material_cost                       │
│ labor_cost                          │
│ overhead_cost                       │
│ unit_cost (total)                   │
└──────────────┬──────────────────────┘
               │ has_many
               ▼
┌─────────────────────────────────────┐
│            BomItem                  │
├─────────────────────────────────────┤
│ bom_id                              │
│ product_id (component)              │
│ quantity                            │
│ unit_cost                           │
│ type: material | labor | overhead   │
└─────────────────────────────────────┘
```

### Key Files

```
Models:
  /app/Models/Accounting/BomVariantGroup.php
  /app/Models/Accounting/Bom.php
  /app/Models/Accounting/BomItem.php

Service:
  /app/Services/Accounting/BomVariantGroupService.php
  /app/Services/Accounting/BomService.php

Controller:
  /app/Http/Controllers/Api/V1/BomVariantGroupController.php
```

### BomVariantGroup Model

```php
// File: /app/Models/Accounting/BomVariantGroup.php

class BomVariantGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'product_id',
        'status',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Bom::class, 'variant_group_id')
            ->orderBy('variant_sort_order');
    }

    public function budgetVariant(): HasOne
    {
        return $this->hasOne(Bom::class, 'variant_group_id')
            ->where('variant_name', 'Budget');
    }

    public function standardVariant(): HasOne
    {
        return $this->hasOne(Bom::class, 'variant_group_id')
            ->where('variant_name', 'Standard');
    }

    public function premiumVariant(): HasOne
    {
        return $this->hasOne(Bom::class, 'variant_group_id')
            ->where('variant_name', 'Premium');
    }

    public function getVariantComparison(): array
    {
        $variants = $this->variants()->with('items.product')->get();

        return [
            'group_name' => $this->name,
            'variants' => $variants->map(fn ($bom) => [
                'id' => $bom->id,
                'name' => $bom->variant_name,
                'material_cost' => $bom->material_cost,
                'labor_cost' => $bom->labor_cost,
                'overhead_cost' => $bom->overhead_cost,
                'total_cost' => $bom->unit_cost,
                'items_count' => $bom->items->count(),
            ]),
        ];
    }
}
```

### BomVariantGroupService

```php
// File: /app/Services/Accounting/BomVariantGroupService.php

class BomVariantGroupService
{
    /**
     * Create variant group with initial variants.
     */
    public function create(array $data): BomVariantGroup
    {
        return DB::transaction(function () use ($data) {
            $group = BomVariantGroup::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'product_id' => $data['product_id'],
                'status' => 'active',
            ]);

            // Create initial variants if provided
            foreach ($data['variants'] ?? [] as $index => $variantData) {
                $this->createVariant($group, $variantData, $index + 1);
            }

            return $group->fresh('variants');
        });
    }

    /**
     * Create a new variant in the group.
     */
    public function createVariant(
        BomVariantGroup $group,
        array $data,
        int $sortOrder
    ): Bom {
        return $this->bomService->create([
            'product_id' => $group->product_id,
            'variant_group_id' => $group->id,
            'variant_name' => $data['variant_name'],
            'variant_sort_order' => $sortOrder,
            'items' => $data['items'] ?? [],
        ]);
    }

    /**
     * Duplicate a BOM to create a new variant.
     * Useful for creating Standard from Budget, swapping components.
     */
    public function duplicateAsVariant(
        BomVariantGroup $group,
        Bom $sourceBom,
        string $newVariantName
    ): Bom {
        return DB::transaction(function () use ($group, $sourceBom, $newVariantName) {
            $newBom = $sourceBom->replicate();
            $newBom->variant_group_id = $group->id;
            $newBom->variant_name = $newVariantName;
            $newBom->variant_sort_order = $group->variants()->count() + 1;
            $newBom->bom_number = $this->bomService->generateNumber();
            $newBom->save();

            // Copy items
            foreach ($sourceBom->items as $item) {
                $newBom->items()->create($item->toArray());
            }

            $newBom->calculateTotals();

            return $newBom->fresh('items');
        });
    }

    /**
     * Get cost comparison across variants.
     */
    public function getCostComparison(BomVariantGroup $group): array
    {
        $variants = $group->variants()
            ->with('items.product')
            ->orderBy('unit_cost')
            ->get();

        $baselineCost = $variants->first()->unit_cost ?? 0;

        return $variants->map(fn ($bom) => [
            'id' => $bom->id,
            'name' => $bom->variant_name,
            'cost' => $bom->unit_cost,
            'diff_from_baseline' => $bom->unit_cost - $baselineCost,
            'diff_percent' => $baselineCost > 0
                ? round(($bom->unit_cost - $baselineCost) / $baselineCost * 100, 2)
                : 0,
            'components' => [
                'material' => $bom->material_cost,
                'labor' => $bom->labor_cost,
                'overhead' => $bom->overhead_cost,
            ],
        ])->toArray();
    }
}
```

### Multi-Option Quotation Integration

```php
// File: /app/Services/Accounting/QuotationService.php

/**
 * Create multi-option quotation from BOM variant group.
 */
public function createFromVariantGroup(
    BomVariantGroup $group,
    array $data
): Quotation {
    return DB::transaction(function () use ($group, $data) {
        $quotation = Quotation::create([
            'contact_id' => $data['contact_id'],
            'quotation_type' => Quotation::TYPE_MULTI_OPTION,
            'bom_variant_group_id' => $group->id,
            'quotation_date' => $data['quotation_date'] ?? now(),
            'valid_until' => $data['valid_until'] ?? now()->addDays(30),
        ]);

        // Create variant options from BOMs
        foreach ($group->variants as $bom) {
            $this->createVariantOption($quotation, $bom, $data['margin_percent'] ?? 0);
        }

        $quotation->calculateTotals();

        return $quotation->fresh(['variantOptions', 'contact']);
    });
}

private function createVariantOption(
    Quotation $quotation,
    Bom $bom,
    float $marginPercent
): QuotationVariantOption {
    $sellingPrice = $bom->unit_cost * (1 + $marginPercent / 100);

    return QuotationVariantOption::create([
        'quotation_id' => $quotation->id,
        'bom_id' => $bom->id,
        'display_name' => $bom->variant_name,
        'cost_price' => $bom->unit_cost,
        'margin_percent' => $marginPercent,
        'selling_price' => $sellingPrice,
        'is_recommended' => $bom->variant_name === 'Standard',
    ]);
}
```

---

## Workflow Example

### Creating Multi-Option Quotation

```
1. Customer requests "Panel MDP 100A" quotation

2. Create BOM Variant Group
   POST /api/v1/bom-variant-groups
   {
     "name": "Panel MDP 100A",
     "product_id": 123,
     "variants": [
       {"variant_name": "Budget", "items": [...]},
       {"variant_name": "Standard", "items": [...]},
       {"variant_name": "Premium", "items": [...]}
     ]
   }

3. Create Multi-Option Quotation
   POST /api/v1/quotations/from-variant-group
   {
     "bom_variant_group_id": 456,
     "contact_id": 789,
     "margin_percent": 25
   }

4. Customer receives quotation with 3 options:
   - Budget:   Rp 56.250.000 (Siemens)
   - Standard: Rp 65.000.000 (Schneider) ← Recommended
   - Premium:  Rp 85.000.000 (ABB)

5. Customer selects "Standard"
   POST /api/v1/quotations/{id}/select-variant
   {"selected_bom_id": 457}

6. Convert to Invoice
   POST /api/v1/quotations/{id}/convert-to-invoice
```

---

## Validation

**Verification Steps:**

1. Variant group links multiple BOMs
2. Each BOM has same `product_id` as group
3. Cost comparison shows differences
4. Multi-option quotation displays all variants
5. Selected variant determines invoice pricing

**Tests:**

```php
// File: /tests/Feature/BomVariantGroups/VariantGroupTest.php

it('creates variant group with three variants', function () {
    $group = BomVariantGroup::factory()
        ->has(Bom::factory()->count(3))
        ->create();

    expect($group->variants)->toHaveCount(3);
});

it('calculates cost comparison correctly', function () {
    $service = app(BomVariantGroupService::class);
    $group = createVariantGroupWithBoms();

    $comparison = $service->getCostComparison($group);

    expect($comparison)->toHaveCount(3);
    expect($comparison[0]['diff_from_baseline'])->toBe(0);  // First is baseline
});
```

---

## References

- ADR-0015: Multi-Option Quotations
- ADR-0016: Quotation from BOM
- ADR-0017: MRP Demand Calculation
- `/docs/02-domain/manufacturing.md`
- `/docs/03-workflows/bom-variants-workflow.md`

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Product Team, Domain Expert
**Reviewers:** Backend Team, UX Team
