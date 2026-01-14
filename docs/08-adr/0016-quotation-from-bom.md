---
adr: "0016"
title: "Quotation from BOM"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [sales, manufacturing]
related_adrs: [0009, 0015]
related_modules: [sales, manufacturing]
impact: medium
---

# ADR-0016: Quotation from BOM

## AI Agent Quick Reference

**Use this ADR when:**
- Creating quotations from BOMs
- Understanding cost-plus pricing
- Building manufacturing quotes
- Working with margin calculations

**Key takeaway:** Quotations can be generated directly from BOMs with automatic margin calculation.

---

## Decision

Quotations can be created directly from BOMs, automatically calculating selling price from BOM cost plus margin.

---

## Implementation

### Flow

```
BOM (cost: Rp 40M) → Add 25% margin → Quotation (price: Rp 50M)
```

### Service Method

```php
// File: /app/Services/Accounting/QuotationService.php

public function createFromBom(Bom $bom, array $data): Quotation
{
    $marginPercent = $data['margin_percent'] ?? 25;
    $quantity = $data['quantity'] ?? 1;

    $costPerUnit = $bom->unit_cost;
    $sellingPrice = (int) round($costPerUnit * (1 + $marginPercent / 100));

    return $this->create([
        'contact_id' => $data['contact_id'],
        'items' => [
            [
                'product_id' => $bom->product_id,
                'description' => $bom->name,
                'quantity' => $quantity,
                'unit_price' => $sellingPrice,
            ],
        ],
        'source_bom_id' => $bom->id,
    ]);
}
```

### Cost Breakdown

```php
// BOM costs included:
$bom->material_cost;    // Raw materials
$bom->labor_cost;       // Labor hours × rate
$bom->overhead_cost;    // Indirect costs
$bom->unit_cost;        // Total = sum of above
```

### Margin Options

| Margin Type | Calculation |
|-------------|-------------|
| Markup on cost | `cost × (1 + margin%)` |
| Gross margin | `cost ÷ (1 - margin%)` |

Default: Markup on cost.

---

## References

- [ADR-0009: BOM Variant Groups](./0009-bom-variant-groups.md)
- [ADR-0015: Multi-Option Quotations](./0015-multi-option-quotations.md)
