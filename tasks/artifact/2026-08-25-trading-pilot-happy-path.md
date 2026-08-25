---
status: done
date: 2026-08-25
type: artifact
persona: Sales, Purchasing, Gudang, Accounting
source_tests:
  - tests/Browser/QuotationTest.php
  - tests/Browser/InvoiceTest.php
  - tests/Browser/PaymentTest.php
  - tests/Browser/DeliveryOrderTest.php
  - tests/Browser/PurchaseOrderTest.php
  - tests/Browser/GoodsReceiptNoteTest.php
  - tests/Browser/BillTest.php
  - tests/Browser/InventoryTest.php
  - tests/Browser/ReportTest.php
  - tests/Browser/PosKopitiamShopTest.php
---

# Trading pilot — one-day happy path

Script for a live tenant. Every step below was driven through the SPA on 2026-08-25 (54 trading browser tests + 17 Kopitiam till tests). Button labels are copied from the UI as it exists today (mixed English / Indonesian).

## Before anyone logs in

| Check | Must be |
|-------|---------|
| `FEATURE_PRESET` | `general` — **not** `pos` (kasir-only hides Faktur / PO / DO / GRN) |
| `FEATURE_POS` | `true` only if Kopitiam till is in this tenant |
| Fiscal period for today | status **Open** (not Locked / Closing / Closed) |
| Master data | 1 customer, 1 supplier, 1 tracked product with on-hand stock, 1 active warehouse (`is_test` = false), CoA seeded (Kas `1-1001`, Bank BCA `1-1010`, Piutang `1-1100`, Pendapatan `4-1001`) |

After changing `.env`: `php artisan optimize:clear`.

**SPA habit:** after Submit / Approve / Post / Ship, the detail page often does **not** refresh by itself. Reload once, then read the status.

**Money:** amounts in the form are rupiah integers (no decimals). PPN 11%.

---

## 1. Sales — Penawaran → Faktur → Bayar → Kirim barang

Login as admin / sales.

### 1.1 Quotation

1. Open `/quotations/new` → **New Quotation**.
2. Pick customer, subject, one line (product + qty).
3. Save. Number looks like `QUO-…`. Status **Draft**.
4. **Submit for Approval** → reload → **Diajukan** (or equivalent submitted).
5. **Approve** → reload → approved.
6. **Convert to Invoice** → land on a draft `INV-…`.

### 1.2 Invoice (or skip 1.1 and create `/invoices/new`)

1. Customer, line description, qty, unit price → submit.
2. Status **Draft**. Click **Post Invoice**.
3. Reload. Status **Terkirim**. A journal exists: Dr Piutang `1-1100`, Cr Pendapatan `4-1001` (+ PPN if taxable). Debit = credit.

### 1.3 Receive payment

1. From the invoice, go to `/payments/new?invoice_id={id}` → **Record Payment**.
2. Customer, amount (partial is OK), cash/bank account (e.g. **Bank BCA**), submit.
3. Toast **Payment recorded successfully**.
4. Invoice **Partial** if not fully paid; **Paid** when outstanding hits zero.
5. Optional: void the payment from the payment detail — JE reverses, invoice status returns.

### 1.4 Delivery order (needs on-hand stock)

1. On the posted invoice: **Create Delivery Order**. Modal: items copy automatically. Confirm.
2. DO `DO-…` is **Draft**.
3. **Confirm** → then **Ship** (confirm in the dialog). Stock on the warehouse **drops**. Movement type out.
4. **Mark Delivered** when the goods arrived.

---

## 2. Purchasing — PO → Terima barang → Tagihan → Bayar

Login as admin / purchasing.

### 2.1 Purchase order

1. `/purchasing/purchase-orders/new` → **New Purchase Order**.
2. Vendor, line description, qty, price → submit. `PO-…` **Draft**.
3. **Submit** → reload → **Diajukan**. Buttons **Approve** / **Reject**.
4. **Approve** → reload → **Disetujui**. **Create GRN** appears.

### 2.2 Goods receipt

1. From the approved PO, create the GRN. Status starts **Draft**.
2. **Start Receiving**.
3. **Complete & Update Inventory**.
4. On-hand stock **rises**. Unit cost is after discount, **without** PPN.

Partial receive: complete a GRN for part of the qty, then a second GRN for the rest. The PO moves to received when the last qty lands.

### 2.3 Bill + pay vendor

1. `/bills/new` — vendor, lines, submit → `BL-…`.
2. **Post Bill** (browser may confirm). Status received. JE: Dr expense/inventory, Cr Hutang.
3. `/payments/new?type=send&bill_id={id}` → **Record Payment**. Vendor, amount, Bank BCA, submit.
4. Full payment zeros AP. Trial balance still balances.

---

## 3. Gudang — Stok tanpa dokumen

Login as admin / inventory.

| Action | Path | What to check |
|--------|------|----------------|
| Stock list | `/inventory` | SKU, quantity, unit |
| Stock in (+) | `/inventory/adjust` → type in, product, warehouse, qty, unit cost, notes | Movement `in`; on-hand up |
| Transfer | `/inventory` transfer UI, two warehouses | Source down, dest up; free stock only (not reserved) |
| Movements | `/inventory/movements` | History by type |
| Stock card | product stock card | Running qty |

Do **not** pick warehouses whose code starts `WH-E2E-` / `WH-OP-` — those are test fixtures (`is_test`).

Kasir Kopitiam (optional, `FEATURE_POS=true`): `/kasir` → pick **Kopitiam 57** outlet → **Mulai jualan** → tap SKU → **Selesai**. Cash over/short at close journals to Selisih Kas.

---

## 4. Accounting — Close the day

Login as admin / accountant. Open `/reports/…`:

| Report | Path | Check |
|--------|------|--------|
| Neraca Saldo | `/reports/trial-balance` | TOTAL debit = TOTAL credit |
| Posisi keuangan | `/reports/balance-sheet` | Assets / Liabilities / Equity |
| Laba rugi | `/reports/income-statement` | Revenue / Expenses |
| Arus kas | `/reports/cash-flow` | Operating / investing / financing |
| Aging AR / AP | `/reports/receivables-aging`, `/reports/payables-aging` | Current bucket present |
| PPN | `/reports/vat` | Page loads |
| Buku besar | `/reports/general-ledger` | Posted lines |
| Stok / valuasi / COGS | `/reports/stock-summary`, `stock-valuation`, `cogs-summary` | Non-zero qty or value rows |

If TB debit ≠ credit: **stop posting** and call engineering. Do not “fix” with `accounts.opening_balance`.

---

## Out of this script (not Phase 1)

Manufacturing (BOM / WO / MR), Solar, bank reconciliation, budget, recurring, year-end close, PPh withholding (flag off), email notifications.

Sales return / purchase return exist in the product and have service tests; they were **not** re-driven in the 2026-08-25 SPA chain. Use them only with someone who already knows the flow.

---

## If something blocks

| Symptom | Likely cause |
|---------|----------------|
| Faktur / PO / Pembayaran missing from sidebar | Tenant is `FEATURE_PRESET=pos` |
| Cannot post invoice / payment | Fiscal period missing, locked, or closed |
| `/payments/new` dumps to login | Payments module off (same as pos preset) |
| Kasir **Mulai jualan** grey, empty gudang | `warehouses.is_test` migration not applied, or only test warehouses |
| Status button still shows after click | Reload the detail page |
| Stock does not move on Ship / Complete GRN | Product `track_inventory` off, or no warehouse |
