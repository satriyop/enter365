---
adr: "0024"
title: "Material Consumption Tracking"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [manufacturing, inventory]
related_adrs: [0013, 0017]
related_modules: [manufacturing, inventory]
impact: medium
---

# ADR-0024: Material Consumption Tracking

## AI Agent Quick Reference

**Use this ADR when:**
- Tracking material usage in production
- Comparing actual vs planned consumption
- Analyzing waste/variance
- Building consumption reports

**Key takeaway:** Track actual material consumption against BOM requirements to identify variances.

---

## Decision

Track actual material consumption per work order and compare against BOM requirements.

---

## Implementation

### MaterialConsumption Model

```php
$table->foreignId('work_order_id');
$table->foreignId('product_id');            // Material consumed
$table->foreignId('warehouse_id');
$table->integer('planned_quantity');        // From BOM
$table->integer('actual_quantity');         // Actually used
$table->integer('variance');                // actual - planned
$table->string('variance_reason')->nullable();
$table->timestamp('consumed_at');
```

### Consumption Flow

```
Work Order Start → Material Requisition → Issue from Warehouse
                        │                        │
                        ▼                        ▼
                  Planned Qty              Actual Qty
                  (from BOM)              (consumed)
                        │                        │
                        └────────┬───────────────┘
                                 ▼
                            Variance
                         (actual - planned)
```

### Recording Consumption

```php
public function consumeMaterial(
    WorkOrder $workOrder,
    Product $material,
    int $quantity
): MaterialConsumption {
    // Get planned from BOM
    $bomItem = $workOrder->bom->items
        ->where('product_id', $material->id)
        ->first();

    $plannedQty = $bomItem
        ? $bomItem->quantity * $workOrder->quantity
        : 0;

    return DB::transaction(function () use ($workOrder, $material, $quantity, $plannedQty) {
        // Record consumption
        $consumption = MaterialConsumption::create([
            'work_order_id' => $workOrder->id,
            'product_id' => $material->id,
            'warehouse_id' => $workOrder->warehouse_id,
            'planned_quantity' => $plannedQty,
            'actual_quantity' => $quantity,
            'variance' => $quantity - $plannedQty,
            'consumed_at' => now(),
        ]);

        // Issue from inventory
        $this->inventoryService->decrementStock(
            $material->id,
            $workOrder->warehouse_id,
            $quantity,
            'work_order',
            $workOrder
        );

        return $consumption;
    });
}
```

### Variance Analysis

```php
public function getVarianceReport(WorkOrder $workOrder): array
{
    return MaterialConsumption::where('work_order_id', $workOrder->id)
        ->with('product')
        ->get()
        ->map(fn ($c) => [
            'product' => $c->product->name,
            'planned' => $c->planned_quantity,
            'actual' => $c->actual_quantity,
            'variance' => $c->variance,
            'variance_percent' => $c->planned_quantity > 0
                ? round($c->variance / $c->planned_quantity * 100, 2)
                : 0,
            'cost_impact' => $c->variance * $c->product->standard_cost,
        ])
        ->toArray();
}
```

### Variance Reasons

| Reason | Description |
|--------|-------------|
| `waste` | Material wasted during production |
| `scrap` | Defective material discarded |
| `rework` | Extra material for corrections |
| `measurement` | Measurement error |
| `quality` | Rejected due to quality |

---

## References

- [ADR-0017: MRP Demand Calculation](./0017-mrp-demand-calculation.md)
- [ADR-0013: Multi-Warehouse Inventory](./0013-multi-warehouse-inventory.md)
- [Manufacturing Domain](../02-domain/manufacturing.md)
