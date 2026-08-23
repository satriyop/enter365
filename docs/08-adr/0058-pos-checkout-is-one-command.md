---
adr: "0058"
title: "POS checkout is one command; last unit is stockOut"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, inventory]
related_adrs: [0049, 0051, 0057]
related_modules: [pos, inventory]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0058: POS checkout is one command; last unit is stockOut

## AI Agent Quick Reference

**Use this ADR when:**
- Designing the POS checkout API
- Tempted to insert line rows as the kasir taps
- Two sessions sell the last unit of a SKU

**Key takeaway:** Cart lives on the tablet until Pay. `checkout(session, lines, discount, tenders)` is one transaction (sale + tenders + stockOut + two JEs). Last unit: `InventoryServiceInterface::stockOut` / `lockForStock` only. Loser gets existing `InsufficientStockException`; nothing posts.

## Context

A POS Sale is never draft. A server cart would be a draft. `stockOut` already serializes product×gudang with `lockForUpdate`. A second POS lock or reserve-on-tap parks stock for carts that may never pay.

## Decision

1. One checkout command. Failure (stok, fiscal period, tender ≠ payable) rolls back entirely.
2. No stock reservation on tap. Untracked/jasa skip `stockOut`.
3. Do not add a POS-level lock beside ADR-0049.
4. Kasir error for last-unit: existing Indonesian `InsufficientStockException` text.

## Consequences

- Held-cart persistence (ticket 18) cannot mean “draft POS Sale”.
- Checkout API is command-shaped, not line CRUD.
