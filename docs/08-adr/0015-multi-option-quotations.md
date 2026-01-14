---
adr: "0015"
title: "Multi-Option Quotations"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [sales, domain]
related_adrs: [0009, 0016]
related_modules: [sales]
impact: medium
---

# ADR-0015: Multi-Option Quotations

## AI Agent Quick Reference

**Use this ADR when:**
- Creating quotations with multiple options
- Understanding Budget/Standard/Premium flows
- Working with QuotationVariantOption
- Building quotation comparison views

**Key takeaway:** Quotations can offer multiple price options (Budget/Standard/Premium) from BOM variants.

---

## Decision

Quotations can include multiple pricing options derived from BOM variant groups, allowing customers to compare and choose.

---

## Implementation

### Data Model

```
Quotation (1) ←→ (N) QuotationVariantOption
                          │
                          └── BomVariantGroup → Bom
```

```php
// QuotationVariantOption
$table->foreignId('quotation_id');
$table->foreignId('bom_id');
$table->string('display_name');           // "Budget (Siemens)"
$table->bigInteger('cost_price');
$table->decimal('margin_percent', 5, 2);
$table->bigInteger('selling_price');
$table->boolean('is_recommended');        // Highlight one option
$table->boolean('is_selected');           // Customer's choice
```

### Creating Multi-Option Quote

```php
// Create from BOM variant group
$quotation = $quotationService->createFromVariantGroup($group, [
    'contact_id' => $customer->id,
    'margin_percent' => 25,
]);

// Result:
// Option 1: Budget (Siemens)   - Rp 56,250,000
// Option 2: Standard (Schneider) - Rp 65,000,000 ← Recommended
// Option 3: Premium (ABB)      - Rp 85,000,000
```

### Customer Selection

```php
// Customer selects an option
$quotationService->selectOption($quotation, $selectedBomId);

// Converts selected option to regular quotation items
// Then can convert to invoice
```

### API Response

```json
{
    "quotation_number": "QUO-202401-0001",
    "is_multi_option": true,
    "variant_options": [
        {
            "id": 1,
            "display_name": "Budget (Siemens)",
            "selling_price": 56250000,
            "is_recommended": false
        },
        {
            "id": 2,
            "display_name": "Standard (Schneider)",
            "selling_price": 65000000,
            "is_recommended": true
        }
    ]
}
```

---

## References

- [ADR-0009: BOM Variant Groups](./0009-bom-variant-groups.md)
- [ADR-0016: Quotation from BOM](./0016-quotation-from-bom.md)
