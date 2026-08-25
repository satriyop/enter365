---
status: done
date: 2026-08-26
type: done
phase: 5
verdict: Go
---

# Phase 5 hardening — Go

**Verdict: Go** for stock-opname SPA, document emails as real listeners, and PPh kept off.

## Stock opname (live SPA, 2026-08-26)

`tests/Browser/StockOpnameTest.php` — **5 passed**, 31 assertions.

- List + create via SPA
- Generate items (in-app modal, not `confirm()`)
- Start counting → Penghitungan
- Count +5 → submit → approve
- `product_stocks.quantity` applies the frozen variance (skill #31)

No `confirm()`/`prompt()` on opname pages. Warehouse fixtures are `is_test` (skill #42).

## Notifications

Not stubs. `NotifyCustomerOnInvoiceSent`, quotation approved/submitted/won, bill received send Laravel Notifications.

Wiring: `LaravelEventDispatcher` → `Event::dispatch()`; `EventServiceProvider` discovers `app/Infrastructure/Listeners`.

Proof: `DocumentLifecycleNotificationTest` includes **dispatches InvoiceSent through Laravel events to the mail listener**.

Production still needs a real mailer (`MAIL_MAILER` not `array`/`log`) and `NOTIFICATION_TEAM_EMAIL`. phpunit uses `array`. Per-event kill switch: `accounting.notifications.*.enabled`.

## PPh — keep off

`FEATURE_PPH_WITHHOLDING` defaults **false**. `PphCalculationService` and contact `is_pph_subject` exist as an Indonesian tax pack, not core trading.

**Decision:** do not enable for this pilot. Enabling later requires HTTP/service tests for bill/payment withholding JEs (Utang PPh 23 / 4(2) / 26) and a product owner sign-off.

## Roadmap

Phases 0–5 on `tasks/roadmap/2026-Q3-go-live.md` are ticked. Explicitly later: Odoo-parity (CRM, RFQ, serial/lot, portal), multi-tenant.
