---
adr: "0061"
title: "POS permission matrix after reclaiming Kasir"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, authorization]
related_adrs: [0042, 0054, 0060]
related_modules: [pos, core]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0061: POS permission matrix after reclaiming Kasir

## AI Agent Quick Reference

**Use this ADR when:**
- Seeding `pos.*` permissions
- Changing `Role::CASHIER` permission list
- Deciding if accountant may checkout

**Key takeaway:** Group `pos`. Kasir operates own session only. Admin any. Accountant reports only. Strip invoices/payments/bills from Kasir.

## Decision

Permissions (group `pos`):

| Name | Meaning |
|------|---------|
| `pos.session.open` | Buka POS Session |
| `pos.session.close` | Tutup sesi — own session unless admin |
| `pos.sale.checkout` | Atomic checkout command |
| `pos.sale.void` | Void — own session unless admin |
| `pos.reports.view` | Sesi/omzet kasir reports (not laba rugi) |

**Kasir:** those four operate perms + `products.view` + `contacts.view`. Not `pos.reports.view`. Not `invoices.*` / `payments.*` / `bills.view`. Not product create.

**Admin:** all `pos.*` (any session).

**Accountant:** `pos.reports.view` only. No checkout.

**Sales / Viewer:** no `pos.*`.

Own vs any is a **policy** on `opened_by`, not extra `_any` flags in V1.

## Consequences

- Cashier seeder description and permission sync must change when POS ships.
- Feature flag `pos` still required on routes; permission is not a substitute.
