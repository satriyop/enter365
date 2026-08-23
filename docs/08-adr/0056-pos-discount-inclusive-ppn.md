---
adr: "0056"
title: "POS discount is header-off-inclusive; PPN extracted per line after"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, tax]
related_adrs: [0008, 0026, 0051, 0055]
related_modules: [pos, accounting]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0056: POS discount is header-off-inclusive; PPN extracted per line after

## AI Agent Quick Reference

**Use this ADR when:**
- Computing POS Sale totals, diskon, or PPN keluaran
- Tempted to reuse Penawaran (discount then exclusive PPN) or Faktur (PPN then subtract discount) math
- Mixed taxable + nontaxable lines in one cart

**Key takeaway:** V1 diskon is header-only, off the **inclusive payable**. Allocate to lines in sen. Each taxable line uses existing `TaxInclusiveStrategy`. Nontaxable: PPN 0. Journal Dr tenders = Cr pendapatan + Cr PPN. HPP unchanged.

## Context

The button is what the customer pays (tax-inclusive when taxable). Penawaran discounts exclusive subtotal then adds PPN — the till would lie. Faktur adds per-line PPN then subtracts discount from the grand total. Extracting 11% from the whole cart over-taxes jasa. Keeping pre-discount PPN after a cut overstates PPN keluaran vs cash in.

## Decision

1. Header diskon only (`%` of inclusive subtotal or Rp sen), capped so payable ≥ 0. Line catalog prices stay. No line diskon in V1.
2. Allocate that integer diskon across lines (last line absorbs remainder sen).
3. Taxable discounted inclusive amount → `TaxInclusiveStrategy` (DPP = round(amount / 1.11), PPN = amount − DPP). Nontaxable → all revenue.
4. Tenders must equal sum of line payables. HPP stays movement cost (ADR-0055).

## Consequences

- Aggregates need header discount type/value/amount plus per-line payable, dpp, ppn after allocation.
- Do not call `InvoiceCalculator` or `QuotationCalculator` from POS.
