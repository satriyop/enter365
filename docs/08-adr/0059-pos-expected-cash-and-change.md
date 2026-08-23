---
adr: "0059"
title: "Expected cash is modal plus net cash tenders; change is not a tender"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos]
related_adrs: [0052, 0057, 0058]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0059: Expected cash is modal plus net cash tenders; change is not a tender

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing tutup kasir
- Recording cash given / kembalian at checkout
- Showing QRIS on the close screen

**Key takeaway:** `expected_cash = opening_cash + sum(cash tenders on completed sales)`. Tender `amount` is applied to payable. Kembalian = cash_received − cash_tender (cash only). QRIS is exact and not in the drawer. Selisih is stored; it does not block close.

## Decision

1. **Expected cash** ignores voided sales and does not subtract kembalian again (tenders are already net).
2. **`cash_difference = counted_cash − expected_cash`**. Close is allowed with selisih.
3. **Checkout:** `cash_received ≥ cash_tender`; `change_amount = cash_received − cash_tender`. Fail if tenders ≠ payable or change < 0. Split: cash tender = payable − qris.
4. **Tutup:** show `expected_qris` as information. No counted QRIS. Selisih is cash-only.

## Consequences

- Sale needs `cash_received_amount` / `change_amount` (or equivalent on the cash tender) without breaking sum(tenders) = payable.
- Kasir can close a short drawer; the shortage is the audit trail.
