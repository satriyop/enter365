---
adr: "0014"
title: "Inventory Costing Methods"
status: accepted
date: 2024-11-01
deciders: [Product Team, Accounting Advisor]
tags: [inventory, accounting]
related_adrs: [0013, 0008]
related_modules: [inventory]
impact: medium
---

# ADR-0014: Inventory Costing Methods

## AI Agent Quick Reference

**Use this ADR when:**
- Understanding inventory valuation
- Calculating COGS
- Implementing costing features
- Building inventory reports

**Key takeaway:** Enter365 supports FIFO, Average, and Standard costing methods.

---

## Decision

Support three inventory costing methods: FIFO, Weighted Average, and Standard Cost.

---

## Implementation

### Costing Methods

| Method | Description | Use Case |
|--------|-------------|----------|
| **FIFO** | First In, First Out | Most accurate, SAK EMKM compliant |
| **Average** | Weighted average cost | Simpler, good for commodities |
| **Standard** | Predetermined cost | Manufacturing with stable costs |

### Product Configuration

```php
// Product model
$table->string('costing_method')->default('average');
$table->bigInteger('standard_cost')->default(0);  // For standard costing
```

### FIFO Implementation

```php
// Track cost layers
class InventoryCostLayer
{
    $table->foreignId('product_id');
    $table->foreignId('warehouse_id');
    $table->integer('quantity');
    $table->bigInteger('unit_cost');
    $table->date('received_date');
}

// Consume oldest first
public function consumeFifo(int $quantity): int
{
    $totalCost = 0;
    $remaining = $quantity;

    $layers = $this->costLayers()
        ->where('quantity', '>', 0)
        ->orderBy('received_date')
        ->get();

    foreach ($layers as $layer) {
        $consume = min($remaining, $layer->quantity);
        $totalCost += $consume * $layer->unit_cost;
        $layer->decrement('quantity', $consume);
        $remaining -= $consume;

        if ($remaining <= 0) break;
    }

    return $totalCost;
}
```

### Average Cost Implementation

```php
// Update average on each receipt
public function updateAverageCost(int $newQty, int $newCost): void
{
    $currentQty = $this->quantity_on_hand;
    $currentValue = $currentQty * $this->average_cost;
    $newValue = $newQty * $newCost;

    $this->average_cost = ($currentValue + $newValue) / ($currentQty + $newQty);
}
```

### Standard Cost

```php
// Use predetermined cost
$cogs = $quantity * $product->standard_cost;

// Variance tracked separately
$actualCost = $grn->unit_cost;
$variance = ($actualCost - $product->standard_cost) * $quantity;
```

---

## References

- [ADR-0013: Multi-Warehouse Inventory](./0013-multi-warehouse-inventory.md)
- [ADR-0008: Integer Currency Storage](./0008-integer-currency-storage.md)
