---
adr: "0053"
title: "Void a POS Sale only while its session is open"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos]
related_adrs: [0051, 0052]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0053: Void a POS Sale only while its session is open

## AI Agent Quick Reference

**Use this ADR when:**
- Kasir charged the wrong sale and the customer already paid
- Someone asks to void or edit yesterday’s POS Sale
- Tempted to use Retur Penjualan for a till mistake

**Key takeaway:** Same open POS Session → **void** the whole POS Sale (reverse stok, journal, tenders). After tutup kasir → no void. No partial-line void in V1. Next-day return is a future **POS Return**, not Retur Penjualan.

## Context

Tutup kasir already compared counted cash to expected cash. Voiding a closed session rewrites that count. Editing a completed POS Sale in place destroys the audit. Reusing Retur Penjualan ties the till to Faktur.

## Decision

Void is a whole-sale reversal on an open session only. Completed POS Sales are immutable except via that void. POS Return (partial or after close) is out of V1.

## Consequences

- Selisih kas on a closed session stays meaningful.
- V1 has no path to take back one line of a three-line sale; kasir voids all and resells.
