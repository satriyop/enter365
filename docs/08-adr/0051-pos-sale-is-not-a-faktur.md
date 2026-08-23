---
adr: "0051"
title: "POS Sale is not a Faktur; one ledger, POS pack"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, sales, architecture, seam]
related_adrs: [0007, 0049]
related_modules: [pos, inventory, accounting]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0051: POS Sale is not a Faktur; one ledger, POS pack

## AI Agent Quick Reference

**Use this ADR when:**
- Adding kasir / Point of Sale behaviour
- Tempted to create an `Invoice` or `DeliveryOrder` from a till checkout
- Deciding whether POS needs its own backend or database
- Classifying POS as a pack vs an industry add-on

**Key takeaway:** A kasir checkout creates a **POS Sale** on this Laravel ledger. It must not create a Faktur or Surat Jalan. POS is a **pack** (flag off by default), not a second product database and not a Vahana-style add-on.

## Context

Enter365 sales are document-shaped: Penawaran → Faktur → Surat Jalan → Pembayaran. A Faktur requires a Pelanggan. UMKM kasir-first acquisition is the opposite: tablet, unnamed buyer, money now, goods at the till.

Two attractive shortcuts both fail: (1) mint a Faktur (and a dummy Pelanggan) per tap, which pollutes Sales and blocks the kasir on document chrome; (2) a separate POS backend, which duplicates stok and CoA and becomes sync debt.

## Decision

1. **One ledger.** This app is system of record. POS posts stock through `InventoryServiceInterface` and posts journals through accounting services. No second POS database of truth.
2. **POS Sale is its own document.** Checkout must not call Invoice, Delivery Order, or Quotation services. If a PKP later needs Faktur Pajak, that is an adapter from POS Sale, not the kasir path.
3. **POS is a pack**, like manufacturing: generic, `pos` feature flag default off, acquisition preset shows kasir + products + stok + accounting and hides Penawaran/Faktur. Not an industry add-on. Isolation may still use add-on-style namespaces and `pos_*` tables so Sales never grows `is_pos`.
4. **Kasir UI is not the ERP SPA chrome.** Dedicated till frontend (separate Vite app or later its own FE repo). Same API.

## Considered Options

| Option | Why rejected |
|--------|----------------|
| Faktur with `source=pos` / dummy Pelanggan UMUM | Contaminates Sales; `contact_id` required; fake Surat Jalan |
| New Sales Order that becomes a Faktur | Enter365 has no Sales Order; still a document chain at the till |
| Separate POS backend | Two stocks, two CoAs, sync; the debt this decision avoids |
| Industry add-on (Vahana-shaped) | Retail kasir is generic, not a vertical |

## Consequences

- Laba rugi and stok include POS Sales without a Faktur existing.
- A POS Sale may have no Pelanggan; do not invent a dummy Contact “UMUM”.
- A POS Sale is paid now. “Catat dulu, bayar minggu depan” is a Faktur, not a Tender.
- Product lines with `track_inventory` stock-out through `InventoryServiceInterface` on the session gudang and **block** when qty is insufficient. Those lines post HPP from the movement cost (ADR-0055). Jasa and untracked products do not move stok.
- Button price is the amount paid: `selling_price_with_tax` when taxable, else `selling_price`. Master `selling_price` stays tax-exclusive. Kasir does not add PPN on top. Header diskon is off that inclusive payable; PPN is extracted per line after (ADR-0056).
- Cash tender posts to the POS Session cash account; QRIS to a configured QRIS/bank account; revenue and PPN follow existing product/tax posting. No POS clearing account.
- Sales module must not import Pos; Pos must not import Sales document services.
- A test that checkout creates an `Invoice` or `DeliveryOrder` is a failing test.
