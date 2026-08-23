---
adr: "0063"
title: "Held cart is session-scoped server state, max 5"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos]
related_adrs: [0052, 0058, 0062]
related_modules: [pos]
impact: medium
supersedes: null
superseded_by: null
---

# ADR-0063: Held cart is session-scoped server state, max 5

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing Simpan / Ambil on the till
- Tempted to insert a draft POS Sale for hold
- Deciding if a reload loses parked pesanan

**Key takeaway:** Simpan stores line+qty on the **open POS Session** (not a PosSale). Max 5. Ambil is a picker. Reload same session keeps them. Tutup kasir drops them. No stock reservation.

## Decision

1. Explicit Simpan only — not tap-as-you-go server cart (ADR-0058).
2. Payload: product id + qty (button price resolved at checkout, not at hold).
3. Invisible to other sessions. Discarded on session close.
4. Sixth Simpan: Indonesian error, must Ambil or discard first.

## Consequences

- Checkout of an Ambil’d basket is still one `checkout(...)` command.
- Not in `pos_sales`. Use table `pos_session_holds` (`pos_session_id`, `lines` json of product_id + qty, timestamps). Not a JSON column on `pos_sessions`, not Redis.
