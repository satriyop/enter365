---
status: done
date: 2026-08-25
type: done
phase: 1
verdict: Go
persona: Sales, Purchasing, Gudang, Accounting
---

# Phase 1 trading pilot — Go

**Verdict: Go** for core trading (Sales + Purchasing + Gudang dasar + Accounting dasar).  
Not a go for manufacturing, Solar, bank recon, budget, recurring, or PPh.

## Evidence (2026-08-25, live SPA + Valet `akuntansi`)

| Suite | Result | What it proves |
|-------|--------|----------------|
| Trading browser chain | **54 passed**, 452 assertions | Quotation, Invoice post+JE, payment partial/full+void, DO confirm/ship/deliver + stock down, PO submit/approve/reject, GRN complete + stock up, Bill post + pay, inventory adjust/transfer, TB/BS/P&L/cash flow/aging/VAT/GL/stock/COGS |
| Kopitiam till | **17 passed**, 133 assertions | Kasir Selesai tunai/QRIS, hold/ambil, owner restock, accountant bounced off till |
| F-14 pgsql locks (CI) | **green** on `main` | `lockForUpdate()` is real on Postgres 16, not sqlite theatre |

Script the pilot user should follow: `tasks/artifact/2026-08-25-trading-pilot-happy-path.md`.

Criteria: `tasks/artifact/test-quality-criteria.md` (JE balanced, stock delta, TB debit = credit). The 2026-08-25 run used `realDb()` on those paths.

## Tenant that this Go assumes

```
FEATURE_PRESET=general
FEATURE_POS=true          # keep till; omit if no kasir
```

`FEATURE_PRESET=pos` is **kasir-only**. It hides invoices/payments/PO/DO/GRN. That is not this Go. Skill #51.

Live `akuntansi` must have the 2026-08-24/25 audit migrations (`credited_amount`, JE signed-exclusive CHECK, `warehouses.is_test`, POS one-open-session index, hot-path FKs). Applied on this machine 2026-08-25.

## Caveats (do not block Phase 1)

| Item | Status | Action |
|------|--------|--------|
| Sales/purchase return SPA | Service tests Strong; not in 2026-08-25 SPA re-run | Use with a trained user, or add to a later chain |
| Stock opname SPA | Service Strong; backlog 004 marked done earlier | Not in this Go |
| Email / notifications | Stub / No-Go | Non-blocking for posting |
| Multi-currency | Soft | Stay IDR |
| POS float / deposit JE | Same Kas account as sales | Needs till-vs-safe product decision |
| Phase 2–5 (MFG, Solar, bank recon, budget) | Out of scope | Separate go/no-go |

## What would turn this into No-Go

- Live tenant left on `FEATURE_PRESET=pos` for a trading customer
- Fiscal period for today not Open
- Trial balance debit ≠ credit after the happy path
- SPA `/payments/new` or `/invoices` missing from the sidebar

## Decision

Pilot users for **Sales, Purchasing, Gudang, Accounting (core)** may start the documented happy path on a `general` tenant.

Do **not** promise Produksi, Solar, or bank reconciliation as part of this pilot.
