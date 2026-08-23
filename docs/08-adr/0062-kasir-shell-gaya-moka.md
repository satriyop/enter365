---
adr: "0062"
title: "Kasir shell pinned to pos-c-gaya-moka.html"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, ux]
related_adrs: [0051, 0053, 0055, 0056, 0058, 0059]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0062: Kasir shell pinned to pos-c-gaya-moka.html

## AI Agent Quick Reference

**Use this ADR when:**
- Building the kasir Vite app
- Arguing about till layout, pay, hold, or tutup kasir screens
- Tempted to copy Moka brand or the older `pos-kasir-tablet.html`

**Key takeaway:** Interaction source of truth is `docs/prototypes/pos-c-gaya-moka.html`. Moka muscle memory (split till), not their wordmark. V1 till: no Diskon, no split tender, period gated at **buka sesi** (and still at checkout if it locks mid-shift).

## Decision

Pin that prototype as the kasir shell: buka sesi → kategori/grid/Pesanan → Bayar (Tunai | QRIS, one Selesai) → lunas + kembalian → Transaksi baru → batalkan struk dengan alasan → tutup with **blind count**. Hold = **Simpan / Ambil (n)**.

Till does not show DPP/PPN; journals still split tax-inclusive (ADR-0056) with payable = sum of button prices.

`docs/prototypes/pos-kasir-tablet.html` is discarded as IA.

## Forks vs earlier POS ADRs

| Earlier | Till V1 |
|---------|---------|
| ADR-0056 header diskon | No Diskon control on till |
| ADR-0059 split cash+QRIS | One method per sale |
| ADR-0055 fail at checkout if period locked | Also refuse **open session** |

## Consequences

- Implementers copy this IA, then rewrite in Vue — do not ship the HTML.
- Held-cart ticket uses Simpan/Ambil language.
