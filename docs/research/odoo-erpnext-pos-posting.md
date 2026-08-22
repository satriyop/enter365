# Research: how Odoo and ERPNext post a POS order to stock and GL

**Ticket:** [Research: how Odoo and ERPNext post a POS order to stock and GL](https://github.com/satriyop/enter365/issues/9)  
**Map:** [POS V1 spec wayfinder](https://github.com/satriyop/enter365/issues/8)  
**Constraint:** ADR-0051 — POS Sale is not a Faktur; checkout must not create Invoice / Delivery Order.  
**Sources:** official docs and source only (Odoo 19.0, ERPNext `develop` / docs.frappe.io). No blog recaps.

**Answer in one sentence:** both systems keep a till document that is *not* the normal SO → Invoice → Delivery chain at checkout; Odoo posts one session journal plus stock pickings (invoice optional, later), ERPNext’s default POS Invoice posts *neither* stock nor GL until closing consolidates it into a Sales Invoice.

---

## Comparison table

| | **Odoo 19 `pos.order`** | **ERPNext POS Invoice (default)** | **ERPNext “Sales Invoice in POS” mode** |
|---|---|---|---|
| **Till document** | `pos.order` on a `pos.session`. Not a `sale.order`. Not an `account.move` unless invoiced. | `POS Invoice` DocType, Python subclass of `SalesInvoice`. Checkout submits this, not a Delivery Note. | Ordinary `Sales Invoice` with `is_created_using_pos`. |
| **Customer** | Optional. Invoice checkbox requires a customer. | **Required.** `validate()` throws if missing. POS Profile can supply a default Customer (walk-in). | Required (Sales Invoice always has a Customer). |
| **When stock qty moves** | Company setting `point_of_sale_update_stock_quantities`: **real time** (default) → one `stock.picking` per paid order; **at session closing** → one picking for the session. Ship Later uses a warehouse delivery instead. | **Not at checkout.** POS Invoice `on_submit` does not call `update_stock_ledger()`. Qty is only *reserved* against Bin via unconsolidated POS Invoice lines. SLE happens when the **consolidated Sales Invoice** submits. | At checkout, if `update_stock` (POS Profile copies this; field is default-on, hidden, read-only). |
| **Stock seam** | `stock.picking._create_picking_from_pos_order_lines` → `_action_done()`. Source = POS picking type location. Not a Sales Order delivery. | No SLE on POS Invoice. At close: merged SI `update_stock_ledger()` (same path as “Sales Invoice with Update Stock”, no Delivery Note). Until then, availability = `Bin.actual_qty − POS reserved qty`. | SI `on_submit` → `update_stock_ledger()` when `update_stock == 1`. |
| **When GL posts** | **Session close** for uninvoiced orders: one `account.move` on the POS orders journal (`pos.session._create_account_move`). Optional per-order customer invoice is a separate `account.move`. Closing also posts cash difference. | **Not at checkout.** POS Invoice `on_submit` does not call `make_gl_entries()`. GLE on the **consolidated Sales Invoice** created by `POS Invoice Merge Log` when `POS Closing Entry` submits. | At checkout: `SalesInvoice.on_submit` → `make_gl_entries()`. Closing only stamps `pos_closing_entry`. |
| **Cash / bank** | Payment method has a **cash or bank journal**. Cash: statement lines against the journal’s cash account. Bank: `account.payment` against the method’s **outstanding** account. Counterpart is an **intermediary POS receivable** (`receivable_account_id` or company `account_default_pos_receivable_account_id`), then reconciled. | Mode of Payment → company `Mode of Payment Account.default_account` (cash/bank). Consolidated SI `make_pos_gl_entries`: Dr cash/bank, Cr customer receivable per payment row. Change: optional Dr receivable / Cr change account (`POS Settings.post_change_gl_entries`). | Same SI POS payment GL at checkout. |
| **Revenue** | Session move **credits income** accounts from product/tax base lines of *uninvoiced* orders (`_get_sale_vals`). Invoiced orders skip that (already on the customer invoice). | On the consolidated SI: credit item `income_account` (Item / Item Group / POS Profile Income Account). | Same, at checkout. |
| **Tax** | Session move tax lines from tax repartition (`_get_tax_vals`). Missing tax accounts block close. | SI tax table → credit tax/charge account heads. POS Profile Taxes and Charges template. | Same. |
| **COGS at the till?** | **No (GL).** Qty may drop at checkout (real-time picking), but COGS/stock-valuation **lines are written on the session move at close**, from pickings of products with `valuation = real_time`. | **No.** Neither qty nor COGS until close-merge. | **Yes, if** perpetual inventory + `update_stock`: SI books warehouse vs expense (COGS) on submit. |
| **Normal sales-invoice chain?** | Checkout does **not** create SO / invoice / picking-from-SO. Invoice is an optional extra (`to_invoice` / Invoice checkbox / later / QR self-service). Ship Later and “settle Sales Order” are shop extras, not the cash-and-carry path. | Checkout does **not** create Delivery Note or Payment Entry. It **does** later mint a **Sales Invoice** (and credit notes) by merging POS Invoices at close. | Checkout **is** a Sales Invoice. That *is* the invoice chain, with Update Stock instead of Delivery Note. |
| **Session** | `pos.session`: open register (opening cash) → sell → close register **and post accounting entries**. | POS Opening Entry → POS Invoices → POS Closing Entry → `consolidate_pos_invoices()`. | Opening/closing still required; closing does not re-post stock/GL. |

---

## How Odoo posts (uninvoiced till sale)

### Documents

1. Cashier validates payment. Frontend syncs via `pos.order.sync_from_ui` → `_process_order` → `_process_saved_order`.
2. `_process_saved_order` (if not draft): `action_pos_order_paid()` (`state = paid`), then `_create_order_picking()`, then if `to_invoice` and an invoice journal is set, `_generate_pos_order_invoice()` (customer `account.move`).
3. On **Close Register**, docs say this posts accounting entries. Source: `action_pos_session_closing_control` → `_validate_session`:
   - if `update_stock_at_closing`: `_create_picking_at_end_of_session()`
   - `_create_account_move(...)` → post `session.move_id`
   - paid uninvoiced orders → `state = done`
   - cash difference via `_post_statement_difference`
   - session `state = closed`

No `sale.order` is created for a normal checkout. Sales orders appear only if the shop feature “settle quotation/order” is used.

### Stock

`res.company.point_of_sale_update_stock_quantities`:

- `real` (default): “Each order sent to the server create its own picking”
- `closing`: “A picking is created for the entire session when it's closed”

`_should_create_picking_real_time` = not `session.update_stock_at_closing`, unless forced (Anglo-Saxon + invoice, or a ship-later refund).

Picking: POS `picking_type_id` source location → customer/stock dest; `_action_done()`. Failures are swallowed in a savepoint (qty may stay undelivered). Services/non-storable lines are skipped (`product_id.type == 'consu'`).

Ship Later (docs): payment screen creates a **delivery order** to the customer address — a warehouse DO, not the till hand-over path.

### GL (session move, uninvoiced)

`_create_account_move` docstring: creates `account.move` / lines; side-effects include reconciling cash receivable, invoice receivable, and stock output.

`_accumulate_amounts` then:

| Bucket | Becomes |
|--------|---------|
| Sales of uninvoiced orders | Credit income (`_get_sale_vals`) |
| Taxes | Credit tax accounts |
| Payments (cash/bank) | Debit POS **intermediary receivable**; cash statement / bank `account.payment` to the method journal |
| `pay_later` | Debit customer receivable (credit / “customer account” tender) |
| Storable + `valuation=real_time` moves | Debit expense (COGS), credit stock valuation |

Cash: “For cash journal, we directly write to the default account in the journal via statement lines.” Bank: outstanding account on the payment method. Intermediary: payment method **Intermediary Account**, else company **Default Account Receivable (PoS)**.

Invoiced orders: customer invoice already holds revenue/tax/receivable; session move only balances POS receivable from invoice payments.

### COGS

Physical stock: at order or at close (setting above). **Inventory valuation journal for uninvoiced POS is session-batched**, not per ticket. Automated (`real_time`) valuation on the product is required or there is no stock-valuation line. Periodic valuation would leave qty in Inventory and GL until stock closing (Accounting inventory-valuation docs) — POS does not special-case that beyond using `product.valuation`.

---

## How ERPNext posts

### Two invoice types (`POS Settings.invoice_type`)

1. **POS Invoice** (default). Fast till document; ledgers deferred.
2. **Sales Invoice**. Real-time SLE + GLE. Creating a POS Invoice is then blocked (`validate_is_pos_using_sales_invoice`).

Official consolidation doc (v13 refactor): POS sales “do not affect the stock and accounting ledgers until a Closing POS Voucher is submitted”; POS Invoice is a “sub-ledger”; close merges them into **one Sales Invoice** that writes 3–4 ledger entries instead of `n × 3`.

### POS Invoice submit (default) — what it does *not* do

`POSInvoice(SalesInvoice)` overrides `on_submit`: loyalty, serial/batch bundles, coupon, payments cleanup. **It does not call `SalesInvoice.on_submit`.** So no `update_stock_ledger()`, no `make_gl_entries()`.

It **does** require Customer and `is_pos` (“Include Payment”). Stock check: `Bin.actual_qty −` submitted unconsolidated POS Invoice qty (`get_pos_reserved_qty`). Negative stock allowed per Item setting.

POS Profile: Warehouse, payment methods, Taxes and Charges, Income Account, Expense Account, Account for Change Amount, Write Off Account, default Customer. `update_stock` default 1, hidden, read-only — applies when something *does* post stock (the merged SI or SI-in-POS).

### Close — where stock and GL actually land

`POS Closing Entry.on_submit` → `consolidate_pos_invoices()` → `POS Invoice Merge Log`:

- `get_new_sales_invoice()`: `Sales Invoice`, `is_pos = 1`
- merge items/payments/taxes, `is_consolidated = 1`, **submit**
- each POS Invoice gets `consolidated_invoice`

That SI `on_submit` is the normal invoice path: `update_stock` → SLE; always `make_gl_entries()`.

`SalesInvoiceGLComposer.compose`:

1. Dr Customer (`debit_to`) Grand Total  
2. Cr tax heads  
3. Cr item income  
4. if `update_stock` and perpetual: parent stock GL (**Dr COGS/expense, Cr warehouse/stock**)  
5. `make_pos_gl_entries`: for each payment, **Cr receivable, Dr mode-of-payment cash/bank**  
6. write-off / rounding / change  

Official perpetual-inventory doc for “Sales Invoice with Update Stock”: “apart from normal account entries for an invoice, Stores and Cost of Goods Sold accounts are also affected based on the valuation amount.” Same mechanics as skipping Delivery Note.

Older POS docs (v12, pre-refactor) showed POS as a paid Sales Invoice immediately:

- Debit Customer (grand total), Debit Bank/Cash (payment)  
- Credit Income, Credit Taxes  

That is the *shape* of the consolidated SI today, not of the till POS Invoice.

### SI-in-POS mode

Checkout creates SI (`is_created_using_pos`). Stock + GL at submit. Closing links invoices; it does not merge. Returns of old POS Invoices still allowed.

---

## COGS (explicit)

| System | Qty at till? | COGS GL at till? | When COGS GL posts |
|--------|----------------|------------------|--------------------|
| Odoo, real-time stock | Yes (picking `_action_done`) | No | Session `account.move` at close, from those pickings if automated valuation |
| Odoo, stock at closing | No | No | Same session move, after session picking |
| ERPNext POS Invoice | No (reserved only) | No | Consolidated SI submit, perpetual + `update_stock` |
| ERPNext SI in POS | Yes if `update_stock` | Yes if perpetual | SI submit |
| **Enter365 (locked)** | Yes, `InventoryServiceInterface` on session gudang, block if short | **Yes, at checkout**, via existing costing (ADR-0014 / 0049), not deferred | POS Sale journal through accounting services |

Neither Odoo nor default ERPNext books COGS as a *per-ticket* GL line at Validate. Both batch it (Odoo: session JE; ERPNext: merged SI). Enter365 must **not** copy that delay: ADR-0051 already posts stock at checkout on the one ledger, so COGS belongs on the POS Sale journal the same way Delivery Order stock-out does — without creating a DO.

---

## What to copy

Given ADR-0051 (one ledger, POS Sale is its own document, `InventoryServiceInterface`, accounting services, no dummy Pelanggan, paid now, no POS clearing account):

1. **A till document that is not Faktur / Surat Jalan.** Odoo’s `pos.order` is the pattern: checkout writes POS Sale only.
2. **Session as the cash envelope.** Open with modal, own the sales, close with counted vs expected (Odoo Closing Register; ERPNext Opening/Closing Entry). Cash difference is a session journal, not a rewrite of sales.
3. **Payment method → cash or bank account.** Odoo cash/bank journal; ERPNext Mode of Payment default account. Cash tender → POS Session cash account; QRIS → configured bank/QRIS account.
4. **Revenue and PPN from product/tax setup**, not a special POS P&L. (Both systems; matches ADR-0051 “revenue and PPN follow existing product/tax posting.”)
5. **Stock-out only for tracked goods; jasa/untracked skip.** Odoo filters storable/consu; ERPNext `is_stock_item` / Bin.
6. **Block oversell of tracked qty.** ERPNext `validate_stock_availablility` (Bin minus other open-session POS qty). ADR-0051 already requires this; when two sessions share a gudang, subtract sibling POS Sales the way ERPNext subtracts unconsolidated POS Invoice qty.
7. **Optional customer on the till ticket** (Odoo). Do not require one to post stock/GL.
8. **Config object for till accounts and gudang** (ERPNext POS Profile / Odoo `pos.config` + payment methods): warehouse, cash account, tax template, income fallback — attached to the POS Session, not to Sales.

---

## What to refuse (ADR-0051)

| Pattern | Who | Why refuse |
|---------|-----|------------|
| Checkout creates / later **merges into Sales Invoice** | ERPNext default close; SI-in-POS whole path | Faktur. “A test that checkout creates an Invoice … is a failing test.” |
| **Dummy / default walk-in Customer** | ERPNext POS Profile `customer` | “Do not invent a dummy Contact UMUM.” |
| **POS receivable / intermediary / clearing account** | Odoo `account_default_pos_receivable_account_id`, payment-method Intermediary Account | ADR-0051: no POS clearing account. Dr cash/QRIS, Cr revenue/PPN (and Dr COGS, Cr inventory) on the POS Sale. |
| **Defer SLE/GLE to tutup kasir** | ERPNext POS Invoice; Odoo session-batched GL | One ledger must include POS Sales as they happen (laba rugi and stok). Close is cash count, not the first time books exist. |
| **`stock.picking` / Delivery Order as the stock document** | Odoo picking; ERPNext Delivery Note (they skip DN but still use SI Update Stock) | Surat Jalan is not till fulfilment. Stock via `InventoryServiceInterface` only (ADR-0049). |
| **`sale.order` settle-from-POS / Ship Later DO** | Odoo shop features | Not V1 cash-and-carry; reopens document sales. |
| **`pay_later` / Customer Account / partial payment** | Odoo `pay_later`; ERPNext `allow_partial_payment` | “Catat dulu, bayar minggu depan is a Faktur, not a Tender.” |
| **POS Invoice as a Sales Invoice subclass** | ERPNext | Isolation: Pos must not import Sales document services; no `is_pos` on Invoice. |
| **Invoice checkbox / self-service Faktur Pajak from the struk** | Odoo | PKP adapter is out of V1 map scope; not the kasir path. |

---

## Enter365 posting sketch (not an implementation)

Locked by ADR-0051–0054; listed so the spec does not drift toward either vendor’s document chain.

On **checkout** of a POS Sale (open POS Session, tenders cover the button price):

1. Persist POS Sale + tenders (no Invoice, DO, Quotation, Pembayaran).
2. For each `track_inventory` line: `InventoryServiceInterface` stock-out on the session gudang; **fail** the checkout if free qty is short (include other open sessions on that gudang).
3. Journal via accounting services, same company CoA:
   - Dr session cash (cash tenders) / Dr QRIS-bank (QRIS tenders)
   - Cr revenue (tax-exclusive selling amount)
   - Cr PPN (if taxable)
   - Dr COGS / Cr inventory at costing-strategy value (tracked lines only)
4. No dummy Pelanggan; no POS clearing; no session-wait for books.

On **void** (same open session): reverse those stock moves and that journal. On **tutup kasir**: cash difference only (Odoo-like), do not mint a Faktur.

---

## Source list

### Odoo (19.0)

| Claim | Source |
|-------|--------|
| Close Register posts accounting entries; cash difference journal | [Workflow — Close the POS register](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/use.html) |
| Invoice is optional; needs customer; Orders vs Invoices journals | [Invoices](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/use/pos_invoices.html) |
| Payment method journal; Intermediary Account; cash vs bank | [Payment methods](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/payment_methods.html) |
| Settle SO from POS; Ship Later creates a delivery order | [Shop features](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/shop.html) |
| Paid → picking → optional invoice | [`pos_order.py` `_process_saved_order`](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/pos_order.py) |
| Real-time vs closing picking | [`pos_order.py` `_should_create_picking_real_time`, `_create_order_picking`](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/pos_order.py); [`res_company.py` `point_of_sale_update_stock_quantities`](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/res_company.py) |
| Session close: picking, session move, cash diff, paid→done | [`pos_session.py` `_validate_session`, `_create_picking_at_end_of_session`, `_create_account_move`, `_accumulate_amounts`](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/pos_session.py) |
| Session JE: income, tax, COGS, stock valuation, POS receivable | same file `_create_non_reconciliable_move_lines`, `_create_stock_valuation_lines`, `_get_sale_vals`, `_get_stock_expense_vals`, `_get_receivable_account` |
| Cash statement vs bank outstanding; intermediary receivable | [`pos_payment_method.py` `journal_id`, `receivable_account_id`, `outstanding_account_id` help](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/pos_payment_method.py) |
| Picking `_action_done` | [`stock_picking.py` `_create_picking_from_pos_order_lines`](https://github.com/odoo/odoo/blob/19.0/addons/point_of_sale/models/stock_picking.py) |

### ERPNext (docs.frappe.io + `develop`)

| Claim | Source |
|-------|--------|
| POS Invoice at Pay/Submit; Opening/Closing; warehouse from Profile | [POS Workflows](https://docs.frappe.io/erpnext/pos-workflows) |
| v13: no SLE/GLE until close; merge to one SI; reserved qty vs Bin | [POS Invoice Consolidation](https://docs.frappe.io/erpnext/pos-invoice-consolidation) |
| Profile: Customer, Warehouse, payments, income/expense/change/write-off, taxes | [POS Profile](https://docs.frappe.io/erpnext/pos-profile) |
| SI + Update Stock = SLE + COGS without Delivery Note; POS as paid retail SI | [Sales Invoice](https://docs.frappe.io/erpnext/sales-invoice); [Perpetual Inventory §2.5](https://docs.frappe.io/erpnext/perpetual-inventory) |
| POS Invoice skips SI `on_submit`; customer required | [`pos_invoice.py` `validate`, `on_submit`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/pos_invoice/pos_invoice.py) |
| Availability = Bin − unconsolidated POS Invoice qty | same file `get_stock_availability`, `get_pos_reserved_qty` |
| Close → merge log → SI `is_pos`, submit | [`pos_closing_entry.py` `on_submit`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/pos_closing_entry/pos_closing_entry.py); [`pos_invoice_merge_log.py` `process_merging_into_sales_invoice`, `get_new_sales_invoice`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/pos_invoice_merge_log/pos_invoice_merge_log.py) |
| SI submit: stock ledger + GL | [`sales_invoice.py` `on_submit`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/sales_invoice/sales_invoice.py) |
| GL: receivable, income, tax, stock/COGS, then Dr cash Cr AR | [`gl_composer.py` `compose`, `make_item_gl_entries`, `make_pos_gl_entries`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/sales_invoice/services/gl_composer.py) |
| Cash/bank account from Mode of Payment | [`pos.py` `get_bank_cash_account`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/sales_invoice/services/pos.py) |
| `invoice_type` POS Invoice vs Sales Invoice; change GL flag | [`pos_settings.json`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/pos_settings/pos_settings.json) |
| Profile `update_stock` default 1, hidden | [`pos_profile.json`](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/doctype/pos_profile/pos_profile.json) |

### Enter365 (lock, not a vendor source)

- [ADR-0051 POS Sale is not a Faktur](../08-adr/0051-pos-sale-is-not-a-faktur.md)
- [ADR-0049 Single stock mutation seam](../08-adr/0049-single-stock-mutation-seam.md)
- [ADR-0052 POS Session](../08-adr/0052-pos-session.md)
- Root `CONTEXT.md` (POS Sale / Tender / Void)

**Not used as authority:** Odoo forum posts, third-party apps, ERPNext v12 page that 404s (content recovered only where still published).
