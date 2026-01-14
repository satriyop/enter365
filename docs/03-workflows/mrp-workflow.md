---
workflow: mrp
title: "MRP Workflow"
entities: [MrpRun, MrpDemand, MrpSupply, PurchaseSuggestion]
services: [MrpService]
tags: [manufacturing, planning, mrp]
---

# MRP Workflow

## AI Agent Quick Reference

**Use this workflow when:**
- Running MRP calculations
- Understanding demand planning
- Generating purchase suggestions
- Debugging material shortages

**Key flow:** Demand Sources → MRP Run → Net Requirements → Purchase Suggestions

---

## MRP Calculation Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        DEMAND COLLECTION                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐ │
│   │ Sales       │   │ Work        │   │ Safety      │   │ Manual      │ │
│   │ Orders      │   │ Orders      │   │ Stock       │   │ Forecast    │ │
│   │             │   │             │   │             │   │             │ │
│   └──────┬──────┘   └──────┬──────┘   └──────┬──────┘   └──────┬──────┘ │
│          │                 │                 │                 │         │
│          └─────────────────┴─────────────────┴─────────────────┘         │
│                                      │                                   │
│                                      ▼                                   │
│                            ┌─────────────────┐                          │
│                            │  GROSS DEMAND   │                          │
│                            │  by date/item   │                          │
│                            └────────┬────────┘                          │
│                                     │                                    │
└─────────────────────────────────────┼────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        SUPPLY COLLECTION                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐                   │
│   │ On-Hand     │   │ Open POs    │   │ Scheduled   │                   │
│   │ Inventory   │   │ (pending)   │   │ Receipts    │                   │
│   │             │   │             │   │             │                   │
│   └──────┬──────┘   └──────┬──────┘   └──────┬──────┘                   │
│          │                 │                 │                           │
│          └─────────────────┴─────────────────┘                           │
│                            │                                             │
│                            ▼                                             │
│                   ┌─────────────────┐                                   │
│                   │  GROSS SUPPLY   │                                   │
│                   │  by date/item   │                                   │
│                   └────────┬────────┘                                   │
│                            │                                             │
└────────────────────────────┼─────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        NET REQUIREMENTS                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│        ┌─────────────────┐         ┌─────────────────┐                  │
│        │  GROSS DEMAND   │    -    │  GROSS SUPPLY   │                  │
│        └────────┬────────┘         └────────┬────────┘                  │
│                 │                           │                            │
│                 └───────────┬───────────────┘                            │
│                             │                                            │
│                             ▼                                            │
│                   ┌─────────────────┐                                   │
│                   │ NET REQUIREMENT │                                   │
│                   │                 │                                   │
│                   └────────┬────────┘                                   │
│                            │                                             │
│               ◇────────────┴────────────◇                                │
│               │                         │                                │
│          Net > 0                   Net <= 0                              │
│          (Shortage)                (Sufficient)                          │
│               │                         │                                │
│               ▼                         ▼                                │
│        ┌─────────────┐           ┌─────────────┐                        │
│        │  Generate   │           │  No Action  │                        │
│        │  Suggestion │           │  Required   │                        │
│        └─────────────┘           └─────────────┘                        │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     PURCHASE SUGGESTIONS                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   For each shortage:                                                     │
│                                                                          │
│   ┌─────────────────────────────────────────────────────────────────┐   │
│   │ Product: MCB 16A 1P                                              │   │
│   │ Required Date: 2024-02-15                                        │   │
│   │ Net Shortage: 50 units                                           │   │
│   │                                                                  │   │
│   │ Consider:                                                        │   │
│   │ - Supplier lead time: 7 days                                     │   │
│   │ - Order date: 2024-02-08                                         │   │
│   │ - MOQ: 25 units → Order qty: 50                                  │   │
│   │ - Preferred supplier: PT ABC Elektrik                            │   │
│   └─────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│   Actions:                                                               │
│   [Convert to PO]  [Ignore]  [Adjust Qty]                               │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## MRP Run Process

```php
// Simplified MRP algorithm
foreach ($items as $item) {
    // 1. Collect gross demand
    $demand = $this->collectDemand($item, $horizon);

    // 2. Collect supply
    $supply = $this->collectSupply($item);

    // 3. Calculate net by date
    $timeline = [];
    $runningBalance = $supply['on_hand'];

    foreach ($demand as $date => $qty) {
        $runningBalance += ($supply['expected'][$date] ?? 0);
        $runningBalance -= $qty;

        if ($runningBalance < $item->safety_stock) {
            $shortage = $item->safety_stock - $runningBalance;
            $timeline[$date] = $shortage;
        }
    }

    // 4. Generate suggestions
    foreach ($timeline as $date => $shortage) {
        $this->createSuggestion($item, $date, $shortage);
    }
}
```

---

## BOM Explosion in MRP

```
Demand: 10 units of "Panel 3-Phase"
Required date: Feb 15

BOM Explosion:
├── MCCB 100A × 10 = 10 pcs (need by Feb 15)
├── MCB 16A × 10 × 12 = 120 pcs (need by Feb 15)
│   └── On hand: 50 pcs
│   └── On order: 0 pcs
│   └── Net shortage: 70 pcs
│   └── SUGGESTION: Order 75 pcs by Feb 8 (7-day lead time)
└── ...
```

---

## Time-Phased View

```
Product: MCB 16A 1P
Safety Stock: 20

Week           |  W1  |  W2  |  W3  |  W4  |  W5  |
---------------|------|------|------|------|------|
Gross Demand   |  30  |  50  |  40  |  60  |  20  |
Scheduled Rcpt |   0  | 100  |   0  |   0  |   0  |
On Hand        |  70  |  40  |  90  |  50  | -10  | ← Shortage!
Net Requirement|   0  |   0  |   0  |   0  |  30  |
Planned Order  |   0  |   0  |   0  |  50  |   0  | ← Order in W4
```

---

## MRP Parameters

| Parameter | Purpose | Example |
|-----------|---------|---------|
| Lead Time | Days to receive | 7 days |
| Safety Stock | Buffer quantity | 20 units |
| MOQ | Minimum order qty | 25 units |
| Lot Size | Order multiple | 10 units |
| Planning Horizon | Days to look ahead | 90 days |

---

## Related Documents

- [ADR-0017: MRP Demand Calculation](../08-adr/0017-mrp-demand-calculation.md)
- [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)
- [Manufacturing Domain](../02-domain/manufacturing.md)
- [Manufacturing Workflow](./manufacturing-workflow.md)

