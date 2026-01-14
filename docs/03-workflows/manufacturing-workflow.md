---
workflow: manufacturing
title: "Manufacturing Workflow"
entities: [Bom, WorkOrder, MaterialRequisition, MaterialConsumption]
services: [BomService, WorkOrderService]
tags: [manufacturing, production]
---

# Manufacturing Workflow

## AI Agent Quick Reference

**Use this workflow when:**
- Implementing work order features
- Understanding BOM explosion
- Tracking material consumption
- Building production reports

**Key flow:** BOM → Work Order → Material Requisition → Production → Completion

---

## Complete Manufacturing Flow

```
                                    ┌─────────────────┐
                                    │  Sales Order    │
                                    │  or Forecast    │
                                    └────────┬────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        BOM SELECTION PHASE                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Select     │───▶│  Configure  │───▶│  Calculate  │                 │
│   │  Product    │    │  Variants   │    │  Materials  │                 │
│   │             │    │  (optional) │    │             │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│         ◇ Has BOM Variants?                     │                        │
│         │                                       │                        │
│    No   │   Yes                                 │                        │
│    ─────┴────▶ Select brand/option              │                        │
│               (ABB/Schneider/Siemens)           │                        │
│                                                 │                        │
└─────────────────────────────────────────────────┼────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        WORK ORDER PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Create     │───▶│  Review     │───▶│  Release    │                 │
│   │  Work Order │    │  Materials  │    │  to Floor   │                 │
│   │  (draft)    │    │  Required   │    │  (released) │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│         {Explode BOM}                           │                        │
│         {Calculate quantities}                  │                        │
│                                                 │                        │
└─────────────────────────────────────────────────┼────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     MATERIAL REQUISITION PHASE                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Generate   │───▶│  Check      │───▶│  Issue from │                 │
│   │  Material   │    │  Stock      │    │  Warehouse  │                 │
│   │  Request    │    │  Available  │    │             │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│         ◇ Stock sufficient?                     │                        │
│         │                                       │                        │
│    Yes  │   No                                  │                        │
│    ─────┴────▶ Create Purchase                  │                        │
│               Requisition / Wait                │                        │
│                                                 │                        │
│                           {Decrement inventory} ◀────────────────────────┤
│                           {Record consumption}                           │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        PRODUCTION PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Start      │───▶│  Record     │───▶│  Quality    │                 │
│   │  Production │    │  Progress   │    │  Check      │                 │
│   │  (started)  │    │             │    │             │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│         {Track labor hours}            ┌────────┼────────┐               │
│         {Record issues}                ▼        ▼        ▼               │
│                                   ┌───────┐ ┌───────┐ ┌───────┐         │
│                                   │ Pass  │ │Rework │ │ Scrap │         │
│                                   └───┬───┘ └───────┘ └───────┘         │
│                                       │                                  │
└───────────────────────────────────────┼──────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        COMPLETION PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Complete   │───▶│  Calculate  │───▶│  Update     │                 │
│   │  Work Order │    │  Variance   │    │  Inventory  │                 │
│   │  (completed)│    │             │    │             │                 │
│   └─────────────┘    └─────────────┘    └─────────────┘                 │
│                                                                          │
│         {Compare actual vs planned}                                      │
│         {Increment finished goods}                                       │
│         {Allocate costs to project}                                      │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Status Transitions

### Work Order Statuses

```
draft ──▶ released ──▶ started ──▶ completed
                  └──▶ cancelled
```

---

## BOM Explosion Example

```
Finished Good: Panel 3-Phase 100A
Quantity: 2 units

BOM Explosion:
├── MCCB 100A (qty: 2 × 1 = 2)
├── MCB 16A 1P (qty: 2 × 12 = 24)
├── Busbar Copper (qty: 2 × 3 = 6)
├── Enclosure 60x40 (qty: 2 × 1 = 2)
└── Terminal Block (qty: 2 × 20 = 40)

With Variant (ABB selected):
├── MCCB 100A [ABB A1N] (qty: 2)
├── MCB 16A 1P [ABB S201] (qty: 24)
└── ...
```

---

## Variance Tracking

| Material | Planned | Actual | Variance | Reason |
|----------|---------|--------|----------|--------|
| MCB 16A | 24 | 26 | +2 | Defective units |
| Wire 2.5mm | 50m | 48m | -2m | Efficient cutting |
| Terminal | 40 | 40 | 0 | On target |

---

## Related Documents

- [ADR-0009: BOM Variant Groups](../08-adr/0009-bom-variant-groups.md)
- [ADR-0024: Material Consumption Tracking](../08-adr/0024-material-consumption-tracking.md)
- [Manufacturing Domain](../02-domain/manufacturing.md)
- [MRP Workflow](./mrp-workflow.md)

