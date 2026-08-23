---
adr: "0064"
title: "POS V1: Sanctum login, config account defaults, checkout idempotency"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, auth, accounting]
related_adrs: [0004, 0052, 0057, 0058]
related_modules: [pos, core, accounting]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0064: POS V1: Sanctum login, config account defaults, checkout idempotency

## AI Agent Quick Reference

**Use this ADR when:**
- Building kasir login
- Resolving cash/QRIS accounts at buka sesi
- Implementing checkout and fearing double-tap

**Key takeaway:** Same Sanctum user as ERP, no PIN. Session snapshots cash from `default_accounts.cash` and QRIS from new `default_accounts.qris` (default bank `1-1002`). No POS settings screen in V1. Checkout requires `Idempotency-Key`; replay returns the existing POS Sale.

## Decision

1. **Auth:** Sanctum email/password. Kasir is a User with role `cashier`. No till PIN, no shared outlet PIN.
2. **Accounts:** At session open, resolve `config('accounting.default_accounts.cash')` and `qris` (add key, default `1-1002`) to account ids and store on the session. Kasir does not pick CoA. Settings UI later.
3. **Idempotency:** `POST` checkout **requires** `Idempotency-Key`. Same key + same open session → same POS Sale, no second `stockOut` or JE. Client sends a new UUID per pay attempt.

## Consequences

- Kasir Vite app uses the same `/api/v1` login as the ERP SPA.
- Adding `qris` to `default_accounts` is part of the POS build, not a new settings module.
- Double Selesai on flaky Wi‑Fi cannot double-sell.
