# Enter365

Indonesian SME ERP (Sales, Purchase, Inventory, Accounting, Manufacturing, Projects) with optional industry add-ons. Counter cash-and-carry is a separate language from document sales.

## Language

### Document sales (existing)

**Faktur**:
A sales invoice to a named Pelanggan, usually after a Penawaran, often with piutang. Creating one requires a Pelanggan.
_Avoid_: using this for a kasir tap-and-pay; POS Sale; Invoice-as-kasir-shortcut

**Penawaran**:
A price quote sent to a Pelanggan before a Faktur.
_Avoid_: POS Sale, order

**Surat Jalan**:
A delivery document that ships goods against a Faktur.
_Avoid_: POS Sale fulfilment (counter sales hand over goods at the till)

**Pelanggan**:
A named buyer stored as a Contact of type customer. Required on a Faktur. Optional on a POS Sale; most till sales have none.
_Avoid_: dummy “UMUM” contacts; requiring a Pelanggan to finish checkout

**Pembayaran**:
Money received against a Faktur (collection of piutang), numbered as a receipt.
_Avoid_: kasir tender at checkout (cash, QRIS, and similar)

**Retur Penjualan**:
A return of goods against a Faktur.
_Avoid_: voiding or returning a POS Sale

### Counter sales (POS)

**POS Sale**:
A completed counter sale, paid when it happens, with goods handed over at the till. It belongs to an open POS Session. Numbered `POS-YYYYMM-NNNN`. Status is `completed` at insert or `voided` — never draft. Created only by one atomic **checkout** command (cart stays on the tablet until Pay). It cannot be piutang. Pricing mode is snapshotted on the session: **inclusive** (tile = amount paid; PPN extracted per taxable line, ADR-0056) or **add** (tile = Harga Cafe; header adds service then PBJT on food+service, ADR-0065). Kopitiam / `FEATURE_PRESET=pos` is add: 5% service then 10% PBJT. Catalog storage stays cafe/exclusive. Till shows Total (and in add mode Subtotal / Service / PBJT) — never DPP/PPN. V1 till has **no diskon control**. Lines with inventory tracking stock-out on the session’s gudang and cannot sell below available qty; those lines also post HPP (Dr HPP Cr Persediaan) from the movement’s cost. Untracked lines do not move stok or HPP. A POS Session cannot be opened, and checkout fails, if the fiscal period is Closed or Locked. Pelanggan is optional.
_Avoid_: Invoice, Faktur, Sales Order, order, transaction, receipt (the paper struk is not the document); draft POS Sale; server cart / line-as-you-tap documents; reserving stok on tap; negative stok; adding PPN on the pay screen; posting PBJT to PPN Keluaran; baking after-tax into the tile in add mode; creating a Faktur to represent the sale; pay-later / catat dulu at the till; calling Faktur COGS strategy from the till; selling into a locked period; Penawaran-style discount-then-PPN

**POS discount**:
Header cut off inclusive payable, PPN extracted per line after. **Not on the V1 till** (no Diskon button). Arithmetic kept if a later till version adds it. Does not change HPP.
_Avoid_: teaching diskon on day-one kasir; line discount; discount off exclusive DPP

**POS Session**:
The period when one kasir is responsible for one till. Status is `open` or `closed` only — no reopen. Opened with modal, one gudang, and snapshotted `cash_account_id` + `qris_account_id` plus pricing_mode / service_rate / tax_add_rate / tax_add_name. Expected cash = modal + cash tenders on **completed** sales (voided excluded; kembalian not subtracted again). Close stores counted cash, expected cash, and selisih; selisih does not block close. QRIS total is shown, not counted. Numbered `PSS-YYYYMM-NNNN`. Handover is close then open. Two tablets may hold two open sessions on the same gudang at once.
_Avoid_: shift (synonym only); a shop-wide day with many kasirs; drawer; cash-up as a synonym for the session itself; one-session-per-gudang; a POS clearing account; reopening a closed session; a `counting` status; putting QRIS into expected cash

**Kasir**:
The till operator. Signs in with the same Sanctum user/password as the ERP (no till PIN in V1). Role `cashier`: `pos.session.open`, `pos.session.close`, `pos.sale.checkout`, `pos.sale.void` (own session only), plus `products.view` and `contacts.view`. Not a Faktur clerk. Admin may close/void any session. Accountant may `pos.reports.view` only. Sales get no `pos.*`.
_Avoid_: `invoices.create` / `payments.create` as the meaning of Kasir; a second role named pos_kasir; outlet-wide PIN; Kasir creating products at the till

**Void**:
Cancels a whole POS Sale while its POS Session is still open. The sale becomes `voided` (reason and timestamps kept). Reverses stok, both journals, and tenders. After tutup kasir there is no void. Partial-line cancel and next-day return are not this (those would be a future POS Return, not Retur Penjualan).
_Avoid_: editing a completed POS Sale; deleting the row; Retur Penjualan; credit note; void after close

**Held cart**:
A parked till basket in `pos_session_holds` on the **open POS Session** (max 5). **Simpan** parks this pesanan; **Ambil** picks from a list. Survives tablet reload on the same session. Other sessions cannot see it. Tutup kasir discards holds. Not a POS Sale. No stok or journal until checkout.
_Avoid_: draft POS Sale; tablet-memory-only hold; two buttons both named Tahan; hold-as-document; unlimited holds

**Tender**:
How the customer pays a POS Sale. V1 till is **Tunai or QRIS**, not both on one sale. Tender `amount` is applied to payable. Kembalian = cash received − cash tender; QRIS is exact. Not a Pembayaran against a Faktur.
_Avoid_: Payment; four competing pay buttons; split on the first-timer till; QRIS change

**Kasir shell**:
The V1 tablet IA is pinned to `docs/prototypes/pos-c-gaya-moka.html`: rail kategori | grid produk | panel Pesanan | Bayar. Moka *habits*, not Moka brand. Kasir sees Total, not DPP/PPN.
_Avoid_: ERP AppLayout on the till; `pos-kasir-tablet.html` as the source of truth

**POS pack**:
An optional Enter365 pack (`pos` / `FEATURE_POS`), like manufacturing — generic, not an industry vertical. Default off except presets `pos` (acquisition) and `full` (demo). The `pos` preset keeps products, stok, gudang, opname, and accounting reports; it hides Penawaran, Faktur, Surat Jalan, purchasing documents, and other ERP packs. Restock is stok masuk / opname. An ERP tenant may set `FEATURE_POS=true` without switching preset.
_Avoid_: industry add-on (Vahana/NEX); `config/addons.php`; a second ledger; a menu item inside the Faktur UI; leaving Penawaran in the nav for a kasir-first tenant
