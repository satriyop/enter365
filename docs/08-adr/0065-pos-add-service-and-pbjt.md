---
adr: "0065"
title: "POS add mode: cafe tile, service then PBJT on the bill"
status: accepted
date: 2026-08-24
deciders: [Product, Engineering]
tags: [pos, tax]
related_adrs: [0051, 0056, 0058, 0062]
related_modules: [pos, accounting]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0065: POS add mode: cafe tile, service then PBJT on the bill

## AI Agent Quick Reference

**Use this ADR when:**
- Computing POS Sale totals for Kopitiam / `FEATURE_PRESET=pos`
- Tempted to store Harga Stlh Tax as `selling_price` or extract 11% PPN from the till button
- Wiring kasir footer, journals, or cafe menu seed

**Key takeaway:** Moka **Add** tax & gratuity. Tile = Harga Cafe. Header: service 5% then PBJT 10% on (cafe + service). Guest pays that total. Journal Cr pendapatan / service / utang PBJT — never PPN Keluaran.

## Context

Kopitiam 57’s menu book lists Harga Cafe. The guest pays Harga Stlh Tax = cafe × 1.05 × 1.10. That 10% is PBJT (pajak restoran), not PPN 11%. ADR-0056 inclusive PPN is the retail till. Putting 25.410 on the Hakau tile would not match the papan.

## Decision

1. Session snapshots `pricing_mode` (`inclusive` | `add`), `service_rate`, `tax_add_rate`, `tax_add_name` at buka kasir.
2. **add**: line unit = `selling_price`. Header `PosAddOnBill` on the cart subtotal (not per line). Tender = payable after add-on.
3. Journal: Dr kas payable, Cr penjualan cafe, Cr pendapatan service, Cr utang PBJT (`4-1005` / `2-1210`).
4. **inclusive** unchanged (ADR-0056). Default unless `FEATURE_PRESET=pos` or `POS_PRICING_MODE=add`.
5. Till shows Subtotal / Service / PBJT / Total. Kasir does not type pajak. No DPP/PPN labels.
6. Bundles, Online, Grab prices stay off the till.

## Consequences

- Re-open the session after changing rates.
- Do not seed after-tax as the catalog price.
- Takeaway skipping service is not this ADR (sales type later).
