---
adr: "0052"
title: "POS Sale belongs to a POS Session"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, architecture]
related_adrs: [0051]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0052: POS Sale belongs to a POS Session

## AI Agent Quick Reference

**Use this ADR when:**
- Opening or closing the till
- Attributing a POS Sale to a kasir or gudang
- Explaining selisih kas after tutup kasir
- Two people sharing a tablet across a handover

**Key takeaway:** Every POS Sale sits on an **open POS Session** (one kasir, one gudang, modal in, counted cash out). Handover is close then open. No shop-wide daily session.

## Context

Without a session, Friday’s cash in the drawer cannot be explained against Friday’s POS Sales. A single shop-wide day mixes kasirs. Letting two people sell on one open session makes selisih kas and theft undebuggable.

## Decision

A POS Session is opened by one kasir with modal, one gudang, and the cash account that will hold cash tenders. POS Sales (and their tenders) attach only to that open session. Close compares expected cash (modal + cash tenders on completed sales; ADR-0059) to counted cash. Handover: close A, open B.

V1 is online-only: a checkout that does not commit is a failed sale, retried by the kasir — no local offline queue.

## Considered Options

| Option | Why rejected |
|--------|----------------|
| No session, just a list of POS Sales | Cannot explain selisih kas |
| One session per shop per day | Mixed kasirs; handover is a lie |
| Offline-first queue in V1 | Conflicts on stok and session; a different product |

## Consequences

- Two tablets may hold two open POS Sessions on the same gudang. Last remaining unit: one checkout succeeds, the other is blocked (ADR-0051 stock rule).
- Void is allowed only while the session is open (ADR-0053).
