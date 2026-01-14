---
workflow: purchasing
title: "Purchasing Workflow"
entities: [PurchaseOrder, GoodsReceiptNote, Bill, Payment]
services: [PurchaseOrderService, GrnService, BillService]
tags: [purchasing, payables]
---

# Purchasing Workflow

## AI Agent Quick Reference

**Use this workflow when:**
- Implementing purchasing features
- Understanding procure-to-pay flow
- Debugging receiving issues
- Building AP reports

**Key flow:** Purchase Order → GRN → Bill → Payment

---

## Complete Purchasing Flow

```
                                    ┌─────────────────┐
                                    │   Reorder       │
                                    │   Point / MRP   │
                                    └────────┬────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        PURCHASE ORDER PHASE                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Create PO  │───▶│  Approve    │───▶│  Send to    │                 │
│   │  (draft)    │    │  (pending)  │    │  Supplier   │                 │
│   │             │    │             │    │  (approved) │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│         ◇ Approval required?                    │                        │
│         │                                       │                        │
│    No   │   Yes                                 │                        │
│    ─────┴────▶ Manager approval                 │                        │
│                                                 │                        │
└─────────────────────────────────────────────────┼────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        RECEIVING PHASE (GRN)                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                 │
│   │  Goods      │───▶│  Create     │───▶│  Verify     │                 │
│   │  Arrive     │    │  GRN        │    │  Quantity   │                 │
│   │             │    │  (draft)    │    │             │                 │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                 │
│                                                 │                        │
│                                    ┌────────────┼────────────┐           │
│                                    ▼            ▼            ▼           │
│                             ┌──────────┐  ┌──────────┐  ┌──────────┐    │
│                             │  Full    │  │  Partial │  │  Quality │    │
│                             │  Receipt │  │  Receipt │  │  Issue   │    │
│                             └────┬─────┘  └────┬─────┘  └────┬─────┘    │
│                                  │             │             │           │
│                                  │             │             ▼           │
│                                  │             │      ┌──────────┐       │
│                                  │             │      │  Return  │       │
│                                  │             │      │  to Supp │       │
│                                  │             │      └──────────┘       │
│                                  ▼             ▼                         │
│                           {Update Inventory}                             │
│                           {Update PO Status}                             │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           BILLING PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐         ┌─────────────┐         ┌─────────────┐       │
│   │  Receive    │────────▶│  Match to   │────────▶│  Approve    │       │
│   │  Supplier   │         │  GRN / PO   │         │  Bill       │       │
│   │  Invoice    │         │             │         │  (approved) │       │
│   └─────────────┘         └─────────────┘         └──────┬──────┘       │
│                                                          │               │
│         ◇ 3-Way Match                                    │               │
│         PO qty = GRN qty = Bill qty?                     │               │
│         PO price = Bill price?                           │               │
│                                                          │               │
│         Mismatch ──▶ Flag for review                     │               │
│                                                          │               │
└──────────────────────────────────────────────────────────┼───────────────┘
                                                           │
                                                           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           PAYMENT PHASE                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐         ┌─────────────┐         ┌─────────────┐       │
│   │  Schedule   │────────▶│  Make       │────────▶│  Bill       │       │
│   │  Payment    │         │  Payment    │         │  Paid       │       │
│   │             │         │             │         │  (closed)   │       │
│   └─────────────┘         └─────────────┘         └─────────────┘       │
│                                                                          │
│         │                         │                                      │
│         ▼                         ▼                                      │
│   {Check due date}          {Create Journal}                             │
│   {Apply retention}         DR Accounts Payable                          │
│                             CR Cash/Bank                                 │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Status Transitions

### Purchase Order Statuses

```
draft ──▶ pending ──▶ approved ──▶ partial ──▶ received
                              └──▶ cancelled
```

### GRN Statuses

```
draft ──▶ received ──▶ inspecting ──▶ accepted
                               └──▶ rejected
```

### Bill Statuses

```
draft ──▶ approved ──▶ partial ──▶ paid
                  └──▶ overdue
```

---

## 3-Way Matching

```
┌─────────────────┐
│  Purchase Order │──┐
│  - Quantity     │  │
│  - Unit Price   │  │
└─────────────────┘  │
                     │     ┌─────────────┐
┌─────────────────┐  ├────▶│   MATCH?    │
│  Goods Receipt  │──┤     │             │
│  - Qty Received │  │     └──────┬──────┘
│                 │  │            │
└─────────────────┘  │      Yes   │   No
                     │       │    │    │
┌─────────────────┐  │       ▼    │    ▼
│  Supplier Bill  │──┘  ┌───────┐ │ ┌───────┐
│  - Qty Billed   │     │Approve│ │ │Review │
│  - Amount       │     └───────┘   └───────┘
└─────────────────┘
```

---

## Retention Handling

For subcontractor bills:

```
Bill Amount:           Rp 100,000,000
Less: Retention (5%):  Rp   5,000,000
Net Payable:           Rp  95,000,000

Retention released after project completion or warranty period.
```

---

## Related Documents

- [ADR-0021: GRN Multi-Step Workflow](../08-adr/0021-grn-workflow.md)
- [ADR-0018: Subcontractor Retention](../08-adr/0018-subcontractor-retention.md)
- [ADR-0020: Sales Purchase Returns](../08-adr/0020-sales-purchase-returns.md)
- [Purchasing Cycle Domain](../02-domain/purchasing-cycle.md)

