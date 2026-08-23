---
adr: "0057"
title: "POS V1 aggregates: Session, Sale, line, Tender"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, data-model]
related_adrs: [0030, 0051, 0052, 0053, 0055, 0056]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0057: POS V1 aggregates: Session, Sale, line, Tender

## AI Agent Quick Reference

**Use this ADR when:**
- Creating `pos_*` tables or API resources
- Adding FKs from POS to Invoice/DO (don't)
- Numbering POS documents
- Hanging journals on a POS Sale

**Key takeaway:** Four aggregates, no Faktur/DO FKs. Session `open`|`closed` (`PSS-`). Sale `completed`|`voided` (`POS-`). Two journal FKs on the sale. Cash/QRIS accounts snapshotted on the session.

## Decision

**POS Session** — `open` | `closed`. No reopen, no `counting`. At open: kasir, gudang, modal (`opening_cash_amount`), `cash_account_id`, `qris_account_id` (defaults from company, then frozen). At close: `counted_cash_amount`, `expected_cash_amount`, `cash_difference_amount`, `closed_at`. Number `PSS-YYYYMM-NNNN`.

**POS Sale** — inserted `completed`; void → `voided` with reason/timestamps (never delete). Optional `contact_id`. Header: inclusive subtotal, discount type/value/amount, payable, dpp, ppn. `journal_entry_id` (Dr cash/QRIS, Cr pendapatan, Cr PPN) and `cogs_journal_entry_id` (Dr HPP, Cr persediaan). Number `POS-YYYYMM-NNNN`. No `invoice_id` / `delivery_order_id`.

**Line** — product, qty, button inclusive, allocated discount, payable, dpp, ppn; `inventory_movement_id` when tracked.

**Tender** — `cash` | `qris` + amount. Sum = payable. Not a Payment.

## Consequences

- Checkout is one command that inserts this seam in a single transaction (ADR-0058). Expected cash and kembalian: ADR-0059.
- Do not reuse `INV-` / `RCV-`.
