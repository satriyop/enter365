---
adr: "0060"
title: "POS pack flag, pos preset, hide document sales"
status: accepted
date: 2026-08-22
deciders: [Product, Engineering]
tags: [pos, configuration]
related_adrs: [0007, 0051]
related_modules: [pos]
impact: high
supersedes: null
superseded_by: null
---

# ADR-0060: POS pack flag, pos preset, hide document sales

## AI Agent Quick Reference

**Use this ADR when:**
- Adding `pos` to `config/features.php`
- Tempted to register POS under `config/addons.php`
- Choosing what a kasir-first tenant sees in the ERP SPA

**Key takeaway:** Pack flag `pos` (`FEATURE_POS`), not an add-on. New preset `pos`. Default on only for `pos` and `full`. Acquisition hides Faktur/Penawaran/DO/purchasing; restock via inventory. Add core flags `invoices` and `payments` so those surfaces can turn off.

## Decision

1. `$packs['pos']` — middleware `feature:pos`. Not in `addons.php`.
2. Preset `pos` plus standalone `FEATURE_POS` override (env wins).
3. **`pos` preset ON:** pos, products, inventory, warehouses, stock_opname. Accounting desk remains (unflagged). Contacts/users/settings remain.
4. **`pos` preset OFF:** quotations, delivery_orders, sales_returns, down_payments, purchase_orders, goods_receipt_notes, purchase_returns, manufacturing family, projects, solar, electrical_panel, budgeting, recurring, multi_currency, bank_reconciliation, **invoices**, **payments**.
5. Introduce `invoices` and `payments` core flags, default **true** except on `pos`.
6. Other presets: `pos` default false except `full`.

## Consequences

- ERP SPA must hide nav from `/api/v1/features`. Kasir Vite app requires `pos`.
- Restock on acquisition is stock-in / opname until purchasing flags are turned on.
- `EnsureFeatureEnabled` on POS routes.
