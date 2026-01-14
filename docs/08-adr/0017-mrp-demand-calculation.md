---
adr: "0017"
title: "MRP Demand Calculation"
status: accepted
date: 2024-11-15
deciders: [Product Team, Domain Expert]
tags: [manufacturing, mrp]
related_adrs: [0009, 0024]
related_modules: [manufacturing, mrp]
impact: high
---

# ADR-0017: MRP Demand Calculation

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing MRP features
- Understanding demand calculation
- Working with purchase suggestions
- Debugging MRP results

**Key takeaway:** MRP calculates net requirements from work orders, explodes BOMs, and suggests purchases.

---

## Decision

Implement Material Requirements Planning (MRP) to calculate material needs and generate purchase suggestions.

---

## Implementation

### MRP Calculation Formula

```
Gross Requirements (from Work Orders + Sales Forecasts)
- Scheduled Receipts (pending POs/GRNs)
- On-Hand Inventory
= Net Requirements

If Net Requirements > 0:
  → Create Purchase Suggestion
```

### MRP Process

```php
// File: /app/Services/Accounting/MrpService.php

public function run(array $options = []): MrpRun
{
    $run = MrpRun::create([
        'run_date' => now(),
        'planning_horizon_days' => $options['horizon'] ?? 30,
        'status' => 'running',
    ]);

    // 1. Collect demand from work orders
    $demands = $this->collectDemand($run);

    // 2. Explode BOMs to get component needs
    $componentNeeds = $this->explodeBoms($demands);

    // 3. Calculate net requirements
    foreach ($componentNeeds as $productId => $need) {
        $onHand = $this->getOnHand($productId);
        $scheduled = $this->getScheduledReceipts($productId);
        $netRequirement = $need['quantity'] - $onHand - $scheduled;

        if ($netRequirement > 0) {
            $this->createSuggestion($run, $productId, $netRequirement, $need['required_date']);
        }
    }

    $run->update(['status' => 'completed']);
    return $run;
}
```

### BOM Explosion

```php
// Recursively explode multi-level BOMs
public function explodeBom(Bom $bom, int $quantity): array
{
    $needs = [];

    foreach ($bom->items as $item) {
        $requiredQty = $item->quantity * $quantity;

        // Check if component has its own BOM (sub-assembly)
        $componentBom = Bom::where('product_id', $item->product_id)
            ->where('status', 'active')
            ->first();

        if ($componentBom) {
            // Recursive explosion
            $subNeeds = $this->explodeBom($componentBom, $requiredQty);
            $needs = $this->mergeNeeds($needs, $subNeeds);
        } else {
            // Raw material - add to needs
            $needs[$item->product_id] = ($needs[$item->product_id] ?? 0) + $requiredQty;
        }
    }

    return $needs;
}
```

### MRP Entities

```php
// MrpRun - calculation run
$table->timestamp('run_date');
$table->integer('planning_horizon_days');
$table->string('status');

// MrpDemand - demand per product
$table->foreignId('mrp_run_id');
$table->foreignId('product_id');
$table->integer('gross_requirement');
$table->integer('scheduled_receipts');
$table->integer('on_hand');
$table->integer('net_requirement');

// MrpSuggestion - purchase suggestions
$table->foreignId('mrp_run_id');
$table->foreignId('product_id');
$table->foreignId('suggested_vendor_id');
$table->integer('suggested_quantity');
$table->date('suggested_order_date');
```

### Converting to PO

```php
$poService->createFromMrpSuggestion($suggestion);
```

---

## References

- [ADR-0009: BOM Variant Groups](./0009-bom-variant-groups.md)
- [ADR-0024: Material Consumption Tracking](./0024-material-consumption-tracking.md)
- [Manufacturing Domain](../02-domain/manufacturing.md)
