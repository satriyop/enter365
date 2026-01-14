---
adr: "0022"
title: "Stock Opname Variance Handling"
status: accepted
date: 2024-11-15
deciders: [Product Team, Accounting Advisor]
tags: [inventory, domain]
related_adrs: [0013, 0011]
related_modules: [inventory]
impact: medium
---

# ADR-0022: Stock Opname Variance Handling

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing physical inventory counts
- Handling stock discrepancies
- Creating variance adjustments
- Building stock opname reports

**Key takeaway:** Stock opname compares physical count vs system, creates adjustment entries for variances.

---

## Decision

Stock opname (physical inventory) records actual counts and automatically creates adjustment entries for variances.

---

## Implementation

### Stock Opname Model

```php
// StockOpname - header
$table->string('opname_number');            // SO-202401-0001
$table->foreignId('warehouse_id');
$table->date('opname_date');
$table->string('status');                   // draft, counting, completed
$table->foreignId('counted_by');
$table->foreignId('approved_by');

// StockOpnameItem - line items
$table->foreignId('stock_opname_id');
$table->foreignId('product_id');
$table->integer('system_quantity');         // What system says
$table->integer('counted_quantity');        // What we counted
$table->integer('variance');                // counted - system
$table->text('variance_reason')->nullable();
```

### Workflow

```
Create Opname → Count Products → Review Variances → Approve → Adjust Stock
     │               │                 │               │
     ▼               ▼                 ▼               ▼
  Snapshot       Record            Explain       Create Journal
  System Qty     Actual Qty        Differences   + Stock Movement
```

### Variance Calculation

```php
public function calculateVariance(StockOpnameItem $item): void
{
    $item->variance = $item->counted_quantity - $item->system_quantity;
    $item->save();

    // variance > 0: surplus (found more than expected)
    // variance < 0: shortage (found less than expected)
}
```

### Adjustment on Approval

```php
public function approve(StockOpname $opname): void
{
    DB::transaction(function () use ($opname) {
        foreach ($opname->items as $item) {
            if ($item->variance !== 0) {
                // Create inventory movement
                InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $opname->warehouse_id,
                    'movement_type' => 'adjustment',
                    'quantity' => $item->variance,
                    'source_type' => StockOpname::class,
                    'source_id' => $opname->id,
                ]);

                // Update product stock
                ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $opname->warehouse_id)
                    ->increment('quantity_on_hand', $item->variance);
            }
        }

        // Create journal entry for variance
        $this->createVarianceJournal($opname);

        $opname->update(['status' => 'completed', 'approved_at' => now()]);
    });
}
```

### Variance Journal Entry

```
Shortage (variance < 0):
DR Inventory Adjustment Expense     Rp X
CR Inventory                        Rp X

Surplus (variance > 0):
DR Inventory                        Rp X
CR Inventory Adjustment Income      Rp X
```

---

## References

- [ADR-0013: Multi-Warehouse Inventory](./0013-multi-warehouse-inventory.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
