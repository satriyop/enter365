---
adr: "0055"
title: "POS Sale posts HPP from movement cost; locked period fails checkout"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, accounting]
related_adrs: [0051, 0049]
related_modules: [pos, accounting, inventory]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0055: POS Sale posts HPP from movement cost; locked period fails checkout

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing POS checkout journals
- Tempted to call `COGSOnInvoiceStrategy` from the till
- A kasir hits a Closed or Locked fiscal period
- Deciding whether V1 POS can skip HPP

**Key takeaway:** Tracked POS Sale lines post Dr HPP Cr Persediaan using `stockOut` movement `total_cost` in the same transaction. Do not use the Faktur COGS strategy. Closed/Locked fiscal periods fail checkout the same way `JournalEntryService::createEntry` does.

## Context

`stockOut` updates qty and costing; it does not journal. Faktur HPP lives on `COGSRecognitionStrategy` typed to Invoice/DeliveryOrder. Skipping HPP at the till overstates persediaan. Faking a Faktur to reuse `onInvoicePost` violates ADR-0051. Parking journals for a locked period is a second queue.

## Decision

1. **HPP at checkout** for `track_inventory` lines: Dr `accounting.default_accounts.cogs`, Cr inventory, amount = movement `total_cost` from the costing strategy. Jasa and untracked lines skip HPP. Void reverses this entry with the rest of the POS Sale.
2. **Do not** call `COGSOnInvoiceStrategy` or add `onPosSale()` to that Faktur-shaped interface. POS posts through `JournalServiceInterface` with `source_type` = POS Sale.
3. **Fiscal period:** refuse **opening a POS Session** if the period is Closed/Locked (kasir never takes money). Checkout still refuses if the period locks mid-shift. Same Indonesian journal errors. No POS bypass, no next-period dating, no qty-only sale. `Closing` is not extra-blocked beyond what Faktur already hits.

## Consequences

- Aggregates may hold a COGS journal id on the POS Sale (or per line).
- Kasir sees an Indonesian period-locked error; accountant must reopen/unlock.
- Laba rugi includes till HPP without a Faktur existing.
