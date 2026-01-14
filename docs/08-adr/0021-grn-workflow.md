---
adr: "0021"
title: "GRN Multi-Step Workflow"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [purchasing, inventory]
related_adrs: [0013, 0020]
related_modules: [purchasing, inventory]
impact: medium
---

# ADR-0021: GRN Multi-Step Workflow

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing goods receipt
- Understanding receiving workflow
- Working with quality inspection
- Handling partial receipts

**Key takeaway:** GRN follows draft → received → inspected → accepted flow with stock updates.

---

## Decision

Goods Receipt Notes support multi-step workflow including quality inspection.

---

## Implementation

### GRN Statuses

| Status | Description | Stock Impact |
|--------|-------------|--------------|
| `draft` | Being created | None |
| `received` | Goods physically received | Stock updated |
| `inspecting` | Quality check in progress | Stock in quarantine |
| `accepted` | Passed QC, ready to use | Stock in main |
| `rejected` | Failed QC | Create purchase return |

### Workflow

```
PO Approved
    │
    ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Draft     │────▶│  Received   │────▶│  Inspecting │
│             │     │ (Stock +)   │     │(Quarantine) │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                                    ┌──────────┴──────────┐
                                    ▼                     ▼
                             ┌─────────────┐       ┌─────────────┐
                             │  Accepted   │       │  Rejected   │
                             │(Main Stock) │       │(Return/Scrap)│
                             └─────────────┘       └─────────────┘
```

### Partial Receiving

```php
// PO: 100 units
// GRN #1: 60 units → PO status: 'partial'
// GRN #2: 40 units → PO status: 'received'

public function receive(GoodsReceiptNote $grn): void
{
    DB::transaction(function () use ($grn) {
        // Update stock
        foreach ($grn->items as $item) {
            $this->inventoryService->addStock(
                $item->product_id,
                $grn->warehouse_id,
                $item->quantity_received,
                'purchase',
                $grn
            );
        }

        // Update PO received quantities
        $this->updatePoReceivedQty($grn);

        $grn->update(['status' => 'received', 'received_at' => now()]);
    });
}
```

### Quality Inspection

```php
public function startInspection(GoodsReceiptNote $grn): void
{
    // Move to quarantine warehouse
    $this->inventoryService->transfer(
        $grn->warehouse_id,
        $quarantineWarehouse->id,
        $grn->items
    );

    $grn->update(['status' => 'inspecting']);
}

public function accept(GoodsReceiptNote $grn): void
{
    // Move from quarantine to main
    $this->inventoryService->transfer(
        $quarantineWarehouse->id,
        $grn->warehouse_id,
        $grn->items
    );

    $grn->update(['status' => 'accepted']);
}
```

---

## References

- [ADR-0013: Multi-Warehouse Inventory](./0013-multi-warehouse-inventory.md)
- [Purchasing Cycle](../02-domain/purchasing-cycle.md)
