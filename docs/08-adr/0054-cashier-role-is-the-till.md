---
adr: "0054"
title: "Kasir role is the till operator, not a Faktur clerk"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, authorization]
related_adrs: [0042, 0051, 0052]
related_modules: [pos, core]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0054: Kasir role is the till operator, not a Faktur clerk

## AI Agent Quick Reference

**Use this ADR when:**
- Assigning who may open a POS Session or checkout
- Touching `Role::CASHIER` permissions
- Tempted to add a `pos_kasir` role

**Key takeaway:** Reclaim `cashier` (display name Kasir) for POS: own session, checkout, void on that session. Strip Faktur/Pembayaran create. Admin can do all POS actions. Do not invent a second kasir role.

## Context

`Role::CASHIER` today is described as “Akses ke faktur dan pembayaran” (`invoices.create`, `payments.create`). That contradicts ADR-0051 (till must not create a Faktur). There are no production tenants, so the stub can change meaning instead of adding `pos_kasir`.

## Decision

Kasir = till operator. Permissions belong on `pos.*` (session open/close, sale, void), not invoice create. Admin/owner retains full POS plus ERP desk. Sales and accountant do not get till checkout by default.

## Consequences

- Existing cashier permission seed must change when POS ships (exact names: ADR-0061).
- A tablet-only hire can be Kasir and never see Penawaran.
