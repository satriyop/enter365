---
adr: "0049"
title: "Single stock mutation seam"
status: accepted
date: 2026-08-13
deciders: [Engineering]
tags: [inventory, architecture, seam]
related_adrs: [0013, 0014]
related_modules: [inventory, manufacturing]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0049: Single stock mutation seam

## AI Agent Quick Reference

**Use this ADR when:**
- Changing warehouse quantity, reserved quantity, or inventory cost
- Issuing material requisitions or consuming work-order materials
- Receiving finished goods from production
- Touching `MovementRecorder`, `ProductStock` writes, or parallel stock helpers

**Key takeaway:** All stock mutations go through one external seam (`InventoryServiceInterface`). No second public write path (including `MovementRecorder` or inline `ProductStock` / `InventoryMovement::create` in domain modules).

---

## Context

Stock at product × warehouse was updated three ways: application inventory operations (lock + costing strategy + events + product sync), a domain `MovementRecorder` (model add/remove, no strategy, no lock, no product sync), and manufacturing inline writes (MR issue, WO reserve/consume). That split caused divergent free-vs-reserved rules, skipped FIFO/weighted-average paths, and double-deduct risk.

### Forces

- **Business:** Free stock must not steal reserved stock for other work orders; manufacturing issue and residual consume must remain correct.
- **Technical:** Costing strategies and concurrency locks only applied on some paths.
- **Maintainability:** Agents and humans invent a fourth write path unless one seam is mandatory.

---

## Decision

1. **External seam:** `InventoryServiceInterface` is the only public module for stock mutations (receipt, issue, adjustment, transfer, production receipt, stock reservation, release, issue against reservation).
2. **Always** use the configured costing strategy for quantity/cost changes; callers may supply unit cost for production receipt; strategy owns how the stock row and cost layers update.
3. **`stockOut` is free-stock only.** Issue that may consume a work order’s reserved quantity is a separate intent (issue against reservation).
4. **Quantities at the seam are integers.** Document modules convert float document lines at the call site (explicit ceil/floor policy).
5. **Migration order:** finished-goods receipt → reservation ops on the seam → material requisition issue → work-order reserve/release/residual consume; then remove `MovementRecorder` as a production path.
6. **Nested transactions:** inventory mutations keep their own transaction wrapping; document modules keep outer transactions (savepoints).

Document modules keep document rules (how much to issue/consume, residual “already issued via MR”, status transitions, manufacturing JE strategies). They do not own inventory physics.

---

## Considered Options

### Option A: InventoryServiceInterface only (chosen)

**Pros:** Matches DO/GRN/returns path already; costing and events exist; deletion test passes for `MovementRecorder` and inline MFG writes.  
**Cons:** Interface grows for reservation; free-stock tightening can affect delivery if reserved stock was previously ignored.

### Option B: Deepen MovementRecorder as physics under InventoryService

**Pros:** Domain-shaped name.  
**Cons:** Second mental model; costing still lives in application services; dual adapters until rewrite is complete.

### Option C: New mutator module with temporary dual adapters

**Pros:** Greenfield naming.  
**Cons:** Three surfaces during migration; higher agent confusion; no payback until InventoryService and MovementRecorder die.

---

## Consequences

- Finished goods, MR issue, and WO material paths must not call `ProductStock` mutation APIs or create movements directly.
- Existing `stockOut` call sites must respect free stock (cannot consume reserved quantity belonging to work orders).
- Dead document helpers such as process-invoice/process-bill stock application remain separate hygiene, not required by this ADR.
- Domain glossary terms for free stock, reserved stock, stock mutation, and issue against reservation live in root `CONTEXT.md`.

---

## Related

- ADR-0013 Multi-warehouse inventory (product × warehouse stock)
- ADR-0014 Inventory costing methods (strategy on stock in/out)
- Architecture review candidate: one warehouse stock mutation path (2026-08-13)
