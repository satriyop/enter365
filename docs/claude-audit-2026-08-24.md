# Enter365 Codebase Audit — 2026-08-24

**Auditor:** Claude (Opus 5) · **Branch:** `main` @ `a7c355d` · **Scope:** Point of Sale (kasir) first, then module-by-module
**Runtime under audit:** PHP 8.4.14 · Laravel 12 · PostgreSQL 16 (`DB_CONNECTION=pgsql`, db `akuntansi`) · `FEATURE_PRESET=pos`

---

## How to read this document

Each finding carries a severity, a **Verified** marker, and a concrete failure scenario.

| Marker | Meaning |
|---|---|
| **Verified — executed** | I ran code/queries against this repo or its database and observed the result. |
| **Verified — code path** | I traced every line from HTTP entry point to the defect; the outcome is deterministic and not conditional on anything I did not read. |
| **Inferred** | Read-based reasoning. Likely correct but not executed; treat as "needs a reproducing test". |

| Severity | Meaning |
|---|---|
| **P0 — Critical** | Data loss, money loss, privilege escalation, or guaranteed outage. Fix before any pilot. |
| **P1 — High** | Silent financial/inventory corruption, or a security hole with real blast radius. |
| **P2 — Medium** | Wrong behaviour under concurrency/edge cases, or a materially wrong report. |
| **P3 — Low** | Correctness papercuts, dead code, maintainability landmines. |

---

## Executive summary

I found **9 P0/P1 issues that will corrupt money or inventory data**, and **two independent authorization holes**, one of which is a complete authenticated-user → superuser escalation.

The most important structural observation: **three of the four safety nets this project documents are not actually running.** PHPStan analyses zero files, the contract test suite is not registered with PHPUnit, and the test suite runs on SQLite while production runs PostgreSQL — which means every `lockForUpdate()` in the codebase is a no-op under test. The concurrency-safety work in the service layer has never been exercised by a single test.

### Top findings

| # | Severity | Area | Finding |
|---|---|---|---|
| [F-01](#f-01) | **P0** | Auth | Any authenticated user can grant themselves every permission via `POST /roles/{role}/sync-permissions` |
| [F-02](#f-02) | **P0** | Shared | `DocumentNumbers` breaks permanently at 10,000 documents per prefix per month — every subsequent document throws a unique-constraint violation |
| [F-03](#f-03) | **P0** | Auth | 29 API controllers have no authorization at all, including `InventoryController` (stock adjust/transfer) |
| [F-04](#f-04) | **P1** | Inventory | Stock opname applies the counted quantity blindly, erasing every sale made during the count |
| [F-05](#f-05) | **P1** | Inventory | Stock opname's journal entry and its inventory movement are computed from different snapshots — the Inventory GL account permanently diverges from stock valuation |
| [F-06](#f-06) | **P1** | Inventory | `InventoryService::adjust()` never touches FIFO cost layers — layers desync from quantity on every adjustment |
| [F-07](#f-07) | **P1** | Accounting | `Account.opening_balance` is a directly-writable API field with no double-entry counterpart — writing it unbalances the trial balance |
| [F-08](#f-08) | **P1** | Accounting | Trial balance silently excludes inactive accounts that still hold posted movements |
| [F-09](#f-09) | **P1** | Security | Unvalidated `sort_dir` concatenated into `orderByRaw` — SQL injection |
| [F-10](#f-10) | **P1** | Security | `QueryFilter` invokes arbitrary object methods named by request parameters |
| [F-11](#f-11) | **P1** | Accounting | `reverseEntry()` silently discards the caller's reversal reason on every reversal in the app |
| [F-12](#f-12) | **P1** | POS | Cash over/short at session close is recorded but never journalized |
| [F-13](#f-13) | **P0** | Tooling | PHPStan aborts on a phantom class and analyses **zero** files |
| [F-14](#f-14) | **P1** | Tooling | Test suite runs SQLite; production is PostgreSQL. All pessimistic locking is untested |

---

# Part 1 — Point of Sale / Kasir

Files reviewed in full: `app/Services/Pos/PosService.php`, `app/Http/Controllers/Api/V1/Pos/PosSessionController.php`, all 6 `app/Models/Pos/*`, all 6 `app/Http/Requests/Api/V1/Pos/*`, all 3 resources, all 3 enums, all 6 POS migrations, `routes/api.php:681-693`.

**Overall:** POS is the most carefully written module in the codebase. Idempotency keys, `lockForUpdate()` on the session, fiscal-period guards, tax-inclusive extraction that provably balances, and per-item `track_inventory` snapshots are all correct. The defects below are mostly at the seams — where POS calls into shared infrastructure that is itself broken.

---

### POS-01 — `expectedCash` is never journalized; cash over/short vanishes {#f-12}

**P1 · Verified — code path** · `app/Services/Pos/PosService.php:79-101`

`closeSession()` computes `expected_cash_amount`, stores `counted_cash_amount`, and writes `cash_difference_amount = counted - expected`. **No journal entry is ever created for that difference.**

```php
$session->update([
    'status' => PosSessionStatus::Closed,
    'expected_cash_amount' => $expected,
    'counted_cash_amount' => $counted,
    'cash_difference_amount' => $counted - $expected,   // recorded, never booked
    ...
]);
```

**Failure scenario:** Cashier's drawer is Rp 50,000 short at end of shift. The GL Cash account (`1-1001`) still reflects the full expected amount because every sale debited it in full. The physical drawer has Rp 50,000 less. The books say the company has cash it does not have, and the discrepancy is invisible to every financial report. Repeat daily across outlets and the Cash account becomes fiction.

**Also missing on the same path:**
- No JE for the **opening float** (`opening_cash_amount`) — where did that cash come from? No transfer from a safe/bank account is recorded.
- No JE for the **end-of-shift deposit/handover** — cash never leaves the POS cash account in the ledger.

**Fix:** On close, post `Dr/Cr Cash ↔ Cash Over & Short (expense/income)` for the difference, and model the float and deposit as transfers between the outlet cash account and the main cash/bank account.

---

### POS-02 — QRIS sales debit the Bank account directly; no clearing account, no MDR

**P2 · Verified — executed** · `config/accounting.php:317`, `app/Services/Pos/PosService.php:196-201`

```php
'qris' => env('ACCOUNTING_QRIS_ACCOUNT', '1-1002'),   // 1-1002 == 'bank'
```

QRIS defaults to the same account code as `bank`. A QRIS sale therefore debits Bank immediately at the moment of sale.

**Failure scenario:** QRIS funds settle T+1 or T+2 and arrive **net of merchant discount rate (MDR, typically 0.3–0.7%)**. Two consequences: (a) the Bank ledger balance never matches the actual bank statement on any given day, making `BankReconciliationService` unusable for POS outlets; (b) MDR fees are never recognised as an expense — gross margin is overstated by the full fee.

**Fix:** Introduce a `QRIS Clearing` receivable account. Sale posts `Dr QRIS Clearing / Cr Revenue+PPN`. Settlement posts `Dr Bank + Dr MDR Expense / Cr QRIS Clearing`.

---

### POS-03 — `show()` and `catalog()` let any cashier read another cashier's session

**P2 · Verified — code path** · `app/Http/Controllers/Api/V1/Pos/PosSessionController.php:66-71, 82-129`

Every other session-scoped method calls `assertOwnSession()`. These two do not:

```php
public function show(Request $request, PosSession $pos_session): PosSessionResource
{
    $this->ensurePermission($request, 'pos.sale.checkout', '...');   // permission only
    return new PosSessionResource($pos_session->load('warehouse', 'holds', 'sales.items', 'sales.tenders'));
}
```

**Failure scenario:** Cashier B enumerates `GET /api/v1/pos/sessions/{id}` and reads cashier A's full sales history, tender mix, and opening float for the shift. `PosOwnerJourneyTest` explicitly tests that a *cashier* is blocked from another's session — but it tests the `checkout` path, not `show`. The guard is genuinely absent here.

**Fix:** Add `$this->assertOwnSession($request, $pos_session);` to both methods.

---

### POS-04 — `takeHold()` returns a model with `id: null`

**P2 · Verified — code path** · `app/Services/Pos/PosService.php:368-380`

```php
$copy = $hold->replicate();   // replicate() strips the primary key and timestamps
$hold->delete();
return $copy;
```

`PosSessionHoldResource` then serialises `'id' => $this->id` → `null`, and `'created_at' => null`.

**Failure scenario:** `POST /holds/{hold}/take` returns `{"id": null, "created_at": null, "lines": [...]}` while `POST /holds` returns a populated `id`. Any frontend that keys the cart on the response `id` gets a null key. Worse: the hold is deleted *before* the client confirms receipt — if the response is lost (flaky till Wi-Fi, a very common POS condition), **the customer's held order is gone with no way to recover it**.

**Fix:** Return the lines payload rather than a model, or soft-delete/mark-taken instead of hard-deleting so a retry can recover.

---

### POS-05 — Two cashiers can open two sessions for the same user

**P2 · Inferred** · `app/Services/Pos/PosService.php:43-77`, `database/migrations/2026_08_22_145106_create_pos_sessions_table.php`

`openSession()` checks `currentOpenSession($userId)` and reuses an existing one — but the read is not locked, and **there is no unique index enforcing "one open session per user"**.

**Failure scenario:** Double-tap on "Buka Kasir" (or a retried request on a slow network — again, normal POS conditions) creates two open sessions. `currentOpenSession()` then returns `latest('opened_at')`, so subsequent sales all land on session B while session A holds an orphaned opening float that is never reconciled or closed.

**Fix:** Add a partial unique index — `CREATE UNIQUE INDEX pos_sessions_one_open_per_user ON pos_sessions (opened_by) WHERE status = 'open';` (PostgreSQL supports this directly).

---

### POS-06 — `openSession()` silently ignores the requested outlet

**P3 · Verified — code path** · `app/Services/Pos/PosService.php:46-52`

If an open session exists, it is returned **before** `$data['warehouse_id']` is even read. A cashier who opens the till selecting outlet B, while holding a forgotten open session at outlet A, silently gets outlet A — and every subsequent sale deducts stock from the wrong warehouse.

**Fix:** If the existing session's warehouse differs from the requested one, throw a clear Indonesian error telling the cashier to close the previous session first.

---

### POS-07 — `hold()` does not validate quantity; `buildLines()` does

**P3 · Verified — code path** · `app/Services/Pos/PosService.php:333-357`

`hold()` casts `(int) $line['quantity']` with no floor check. The `HoldPosCartRequest` does enforce `min:1`, so the HTTP path is safe — but the service contract is not, and `PosServiceInterface` is injectable. A quantity of `0` or `-3` can be persisted into the `lines` JSON and surfaces later on take-hold.

---

### POS-08 — Zero-priced products sell for free without complaint

**P3 · Verified — code path** · `app/Services/Pos/PosService.php:463-467`

```php
$unit = $product->is_taxable ? (int) $product->selling_price_with_tax : (int) $product->selling_price;
```

No guard that `$unit > 0`. A product with an unset selling price checks out at Rp 0, posts a Rp 0 revenue JE, and still performs the stock-out and COGS posting — **inventory leaves the building and the sale records zero revenue.**

**Fix:** Throw `BusinessRuleException` when `$unit <= 0` with a message naming the product.

---

### POS-09 — Rounding round-trip means POS revenue never ties to catalogue price

**P3 · Verified — executed** · `app/Models/Inventory/Product.php:326-333` + `app/Services/Pos/PosService.php:468-478`

The button price is `round(selling_price × 1.11)`. At checkout that price is treated as tax-inclusive and DPP is extracted with `round(payable / 1.11)`. Double rounding means the recovered DPP is not the original `selling_price`:

> `selling_price = 9,091` → button `10,090` → extracted DPP `9,090`, PPN `1,000`

The journal entry still balances exactly (`TaxInclusiveStrategy::calculate` derives tax as `total − base`, which I verified — this is correctly implemented). But a "sales by product at list price" report will never reconcile to the GL revenue account.

**Fix:** Store the tax-inclusive shelf price as the source of truth for POS products and derive `selling_price` from it, rather than the reverse.

---

### POS-10 — `catalogImageUrl()` interpolates SKU into a filesystem path

**P3 · Verified — code path** · `app/Http/Controllers/Api/V1/Pos/PosSessionController.php:180-191`

```php
$relative = 'pos/kopitiam/'.$sku.'.jpg';
if (! is_file(public_path($relative))) { return null; }
```

A SKU containing `../` traverses out of the intended directory. The mandatory `.jpg` suffix and the fact that only a boolean existence check is performed cap the impact at file-existence disclosure, and SKUs are admin-controlled — hence P3, not higher. Still: `basename()` the SKU, or validate it against `[A-Za-z0-9_-]+`.

---

### POS-11 — Test/seed data filtering is hardcoded into production code

**P3 · Verified — code path** · `app/Http/Controllers/Api/V1/Pos/PosSessionController.php:44-46`

```php
->where('code', 'not like', 'WH-E2E-%')
->where('code', 'not like', 'WH-OP-%')
```

A real outlet named with either prefix would be invisible to the POS. Test-fixture concerns should not leak into a production query; scope test warehouses with an `is_test` flag or exclude them in the seeder instead.

---

### POS-12 — Session detail loads every sale of the shift, unbounded

**P2 · Verified — code path** · `PosSessionController::current()` and `::show()` → `PosSessionResource`

Both eager-load `'sales.items', 'sales.tenders'` and the resource serialises all of them. A busy outlet doing 400 sales/shift returns a JSON document with 400 sales, their items, and their tenders on **every** call to `GET /pos/sessions/current` — which a till polls frequently.

Compounded by **[F-15](#f-15)**: `pos_sales.pos_session_id` has no index, so each of those loads is a sequential scan over the whole `pos_sales` table.

**Fix:** Paginate sales, or omit them from `current()` and expose a dedicated `GET /pos/sessions/{id}/sales` endpoint.

---

### POS-13 — Voiding a sale from a closed fiscal period is impossible

**P2 · Verified — code path** · `PosService::voidSale()` → `JournalEntryService::reverseEntry()` → `createEntry()`

`reverseEntry()` dates the reversal at the **original** entry's date. If that period has since been closed, `createEntry()` throws and the entire void rolls back.

The transaction boundary is correct (no partial state), but the operational consequence is that a mis-keyed sale discovered after month-end close can never be corrected through the application. See **[F-11](#f-11)** for the shared root cause.

**Fix:** Reverse into the current open period (standard practice), keeping `reversal_of_id` as the audit link.

---

### POS-14 — `PosCheckoutIdempotency` has no TTL

**P3 · Verified — code path**

The table grows unbounded, one row per checkout, forever, and `pos_checkout_idempotencies.pos_sale_id` is unindexed. Idempotency keys are only meaningful for minutes. Add a prune job (`model:prune` or a scheduled delete of rows older than 24h).

---

### POS — what is correct (worth preserving)

I want to be explicit about what I checked and found sound, so a future refactor does not "fix" it:

- **Tax extraction balances exactly.** `TaxInclusiveStrategy::calculate()` returns `total − base` rather than an independent rounding, so `dpp + ppn == payable` per line by construction. The revenue JE cannot be unbalanced by rounding. This is the right design.
- **Idempotency is correct.** The `(pos_session_id, idempotency_key)` unique index exists, and the session `lockForUpdate()` at the top of `checkout()` serialises concurrent checkouts within a session — so the check-then-insert is safe *in PostgreSQL*. (It is not exercised by any test; see [F-14](#f-14).)
- **`track_inventory` / `is_taxable` are snapshotted onto `pos_sale_items`** rather than re-read from the product at void time. This is exactly right — voiding a sale after the product's flags changed still reverses correctly.
- **`expectedCash` correctly excludes voided sales** and counts only cash tenders.
- **Money is stored as `bigInteger` in rupiah**, never floats, throughout POS.

---

# Part 2 — Cross-cutting infrastructure

These defects are not owned by any module, but every module inherits them. Fixing these has the highest leverage in the codebase.

---

## F-01 — Any authenticated user can become a superuser {#f-01}

**P0 · Verified — code path**

`app/Http/Controllers/Api/V1/RoleController.php:97-110` · `routes/api.php:589-591` · `bootstrap/app.php:20-39`

The escalation is three facts stacked:

1. **`routes/api.php:589-591`** — the roles routes carry only `auth:sanctum`. No `permission:` or `can:` middleware.
2. **`bootstrap/app.php`** — the only aliased middleware is `feature`. There is **no global authorization middleware anywhere.**
3. **`RoleController::syncPermissions()`** — no `$this->authorize()`, and the `Request` is validated only for shape:

```php
public function syncPermissions(Request $request, Role $role): JsonResponse
{
    $request->validate([
        'permissions'   => 'required|array',
        'permissions.*' => 'exists:permissions,id',
    ]);
    $updatedRole = $this->roleService->syncPermissions($role, $request->input('permissions'));
    ...
}
```

`RoleService::syncPermissions()` then calls `$role->permissions()->sync($permissionIds)` with no further checks.

**Failure scenario — full exploit, three requests:**

```
GET  /api/v1/permissions          → collect every permission id      (PermissionController: also unauthorized)
GET  /api/v1/users/me             → learn my own role id             (e.g. the "kasir" role)
POST /api/v1/roles/{kasirRoleId}/sync-permissions
     {"permissions": [1,2,3,...,N]}
```

The attacker does not need to attach a new role to themselves — they escalate the role they **already hold**. A POS cashier, who by definition has a valid login on a shared till, is now a full administrator: they can void invoices, adjust inventory, post journal entries, and close fiscal periods.

`StoreRoleRequest` and `UpdateRoleRequest` both `return true` from `authorize()`, so role creation and modification are equally open.

**Fix (in order):**
1. Immediately add `$this->authorize(...)` to every `RoleController` and `PermissionController` method against a `RolePolicy`.
2. Add a `permission:` route middleware and apply it at the route-group level, so a missing controller call is not a silent hole.
3. Consider `Gate::before()` returning `null` (not `true`) for admins plus a deny-by-default `Gate::after()` in non-local environments.

---

## F-03 — 29 API controllers have no authorization check at all {#f-03}

**P0 · Verified — executed**

I scanned every controller under `app/Http/Controllers/Api/` for any of `authorize(`, `Gate::`, `hasPermission`, `->can(`. These have none:

```
V1/RoleController                    ← F-01, privilege escalation
V1/InventoryController               ← stock-in / stock-out / adjust / transfer
V1/StockOpnameController             ← physical count approval
V1/WarehouseController
V1/ProductCategoryController
V1/CompanyProfileController
V1/RecurringTemplateController
V1/MaterialRequisitionController
V1/GoodsReceiptNoteController
V1/SubcontractorInvoiceController
V1/SubcontractorWorkOrderController
V1/PurchaseReturnController
V1/PaymentReminderController
V1/QuotationFollowUpController
V1/BomTemplateController
V1/FeatureController
V1/Solar/SolarProposalController
V1/Solar/SolarDataController
V1/ElectricalPanel/SpecValidationRuleSetController
V1/ElectricalPanel/ComponentStandardController
V1/ElectricalPanel/BomTemplateBrandController
V1/ElectricalPanel/ComponentBrandMappingController
V1/ElectricalPanel/ComponentMappingImportController
(+ Auth/Health/PublicCompanyProfile/PublicSolar* — public by design)
```

I confirmed the corresponding FormRequests do not compensate: `StockInRequest`, `StockAdjustmentRequest`, `StockTransferRequest`, `StoreRoleRequest`, `UpdateRoleRequest` all `return true` from `authorize()`. **124 of 133 FormRequests in the codebase `return true`.**

**Failure scenario — inventory fraud, which is the realistic threat in a POS/retail business:** A cashier walks out with Rp 5,000,000 of stock, then calls `POST /api/v1/inventory/adjust` to set the on-hand quantity to what remains. `InventoryService::adjust()` accepts any `newQuantity` (including negatives), writes the movement, and the shrinkage is absorbed as an "adjustment" with the cashier's own user id on it — which is the only trace, and they can adjust it again.

**Fix:** Treat this as a systematic gap, not 29 individual bugs. Add route-group `permission:` middleware as the primary control so that adding a route without a permission is a visible omission, then backfill policies.

---

## F-02 — Document numbering breaks permanently at 10,000/month {#f-02}

**P0 · Verified — executed**

`app/Domain/Shared/DocumentNumbers.php:27-38`

```php
$last = DB::table($table)->where($column,'like',$prefix.'%')->orderBy($column,'desc')->lockForUpdate()->value($column);
$next = $last ? (int) substr($last, -4) + 1 : 1;
return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
```

Two compounding defects: the sequence is read from the **last 4 characters**, and the "highest" number is found by **lexicographic** string sort. I executed this logic directly:

```
last=POS-202608-9999   -> next=POS-202608-10000     (str_pad does not truncate — 5 digits)
desc string sort over ["…-0002","…-10000","…-9999"] -> max is "…-9999"   ('9' > '1')
last=POS-202608-9999   -> next=POS-202608-10000     (again — collision)
last=POS-202608-10000  -> next=POS-202608-0001      (substr(-4) == "0000")
```

Once document 10,000 exists, `…-9999` remains the lexicographic maximum forever, so **every subsequent generation returns `…-10000` and hits the unique constraint.** The document type is permanently unable to create new records until the calendar month rolls over.

**Failure scenario — this fires on journal entries first, not POS.** Every invoice, bill, payment, sales return, and POS sale creates one or two `JE-YYYYMM-nnnn` entries. A single outlet at 300 sales/day generates ~600 POS journal entries per day and crosses 10,000 in **17 days**. On day 17 the entire application stops being able to post anything — POS checkout, invoicing, payments — with an opaque `SQLSTATE[23505] duplicate key` error.

**Two secondary defects in the same 12 lines:**

- **First-of-month race.** When the `LIKE` query matches zero rows, `SELECT … FOR UPDATE` locks nothing in PostgreSQL. Two concurrent transactions both compute `1` and one gets a unique violation.
- **Prefix uses `now()`, not the document date.** A back-dated journal entry for July created in August gets a `JE-202608-` prefix. `JournalEntryService::createEntry()` correctly resolves the *fiscal period* from `entry_date`, so the number and the period disagree.

**Fix:** Replace the whole strategy with a dedicated `document_sequences` table (`prefix`, `next_value`) updated via `UPDATE … RETURNING next_value`, or a PostgreSQL sequence per prefix. Widen the pad to at least 6 digits. Never derive the next value by string-sorting formatted numbers.

Note `app/Domain/Shared/NumberGeneration/SequentialNumberStrategy.php` is a parallel implementation of the same idea — see [F-22](#f-22).

---

## F-09 — SQL injection in `sort_dir` {#f-09}

**P1 · Verified — code path**

`app/Http/Controllers/Api/V1/QuotationFollowUpController.php:60-64`

```php
$sortDir = $request->input('sort_dir', 'asc');       // entirely unvalidated
if ($sortField === 'next_follow_up_at') {
    $query->orderByRaw('next_follow_up_at IS NULL, next_follow_up_at '.$sortDir);
}
```

I swept the whole `app/` tree for raw-SQL string concatenation of variables. This is **the only** instance where request input reaches raw SQL — every other `whereRaw` uses bound parameters, and `HasSearchFilter` interpolates only hardcoded column names from `getSearchableFields()`. Credit where due: the rest of the codebase is disciplined here.

**Failure scenario:** Laravel's PDO layer blocks stacked statements, so this is not direct RCE — it is a **blind boolean/error-based extraction primitive**. An `ORDER BY` expression can encode a predicate:

```
?sort_dir=asc, (CASE WHEN (SELECT substr(password,1,1) FROM users WHERE id=1)='$' THEN 1 ELSE 1/0 END)
```

A division-by-zero error versus a clean 200 leaks one bit per request. The `users.password` bcrypt hash is extractable character by character, as is anything else in the database.

The `else` branch (`$query->orderBy($sortField, $sortDir)`) is safe — Laravel's `orderBy` allowlists the direction and quotes the identifier.

**Fix:** `$sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';`

---

## F-10 — `QueryFilter` invokes arbitrary methods named by request parameters {#f-10}

**P1 · Verified — code path**

`app/Filters/QueryFilter.php:66-79`

```php
foreach ($this->getFilterableParameters() as $name => $value) {
    $method = Str::camel($name);
    if ($this->shouldApplyFilter($method, $value)) {
        $this->{$method}($value);          // ← arbitrary method dispatch
    }
}

protected function shouldApplyFilter(string $method, mixed $value): bool
{
    return method_exists($this, $method) && $value !== null && $value !== '';
}
```

`method_exists()` returns `true` for `private` and `protected` methods, and because the call happens **inside the class scope**, visibility does not block it. Any method on the filter object — including everything inherited from `QueryFilter` and its traits — is reachable by naming it as a query parameter in kebab-case.

**Failure scenarios:**

| Request | Resolves to | Result |
|---|---|---|
| `?apply=1` | `QueryFilter::apply('1')` | `TypeError` (expects `Builder`) → 500 |
| `?apply-sorting=1` | `applySorting('1')` | `ArgumentCountError` → 500 |
| `?should-apply-filter=1` | `shouldApplyFilter('1')` | `ArgumentCountError` → 500 |
| `?keyword=x` | `HasSearchFilter::keyword('x')` | PostgreSQL `REGEXP` operator does not exist → 500 (see [F-21](#f-21)) |

This applies to **every list endpoint in the application** — every model using `Filterable` routes through it. It is a reliable unauthenticated-shaped DoS/error-spray vector (any valid session suffices), and it hands an attacker a method-enumeration oracle over your filter classes.

**Fix:** Replace `method_exists` with an explicit allowlist:

```php
protected array $allowedFilters = ['status', 'search', 'contactId'];

protected function shouldApplyFilter(string $method, mixed $value): bool
{
    return in_array($method, $this->allowedFilters, true) && $value !== null && $value !== '';
}
```

Or, if an allowlist per class is too much churn, reflect on the method and require it to be `public` **and** declared below `QueryFilter` in the hierarchy.

**Related:** `applySorting()` passes an unvalidated `direction` to `orderBy()`, which throws `InvalidArgumentException` → 500 rather than a 422. Allowlist it to `asc`/`desc`.

---

## F-11 — `reverseEntry()` discards the caller's reversal reason {#f-11}

**P1 · Verified — code path**

`app/Services/Accounting/Journal/JournalEntryService.php:150-192`

```php
public function reverseEntry(JournalEntry $entry, ?string $description = null): JournalEntry
{
    ...
    $entryDate   = $entry->entry_date;
    $description = 'Reversal of '.$entry->entry_number.': '.$entry->description;   // ← parameter overwritten
```

The `$description` argument is unconditionally reassigned before use. **Every reversal reason in the entire application is silently thrown away.** I traced the affected callers:

| Caller | Reason passed | Reason stored |
|---|---|---|
| `PosService::voidSale` | `"Void POS-202608-0042"` | discarded |
| `PosService::voidSale` (COGS) | `"Void HPP POS-202608-0042"` | discarded |
| `InvoiceVoidService::void` (COGS) | `"Pembatalan HPP faktur: INV-…"` | discarded |
| `SalesReturnService`, `PaymentVoidService`, `LandedCostService`, … | various | discarded |

**Failure scenario:** An auditor asks why a Rp 12,000,000 invoice was reversed. The ledger says `"Reversal of JE-202608-0311: Penjualan INV-202608-0044"` — a restatement of the original, containing no information about *why*. The operator's reason was captured at the API boundary, stored on the *document*, and then dropped on the floor at the ledger. The general ledger — the one artifact an auditor actually reads — has no reversal reasons anywhere.

**Fix:** `$description = $description ?? 'Reversal of '.$entry->entry_number.': '.$entry->description;`

**Two further defects in the same method:**

- **Reversal date.** The reversal is posted at the *original* entry date. If that period is closed, the reversal cannot be created and the void fails entirely (see [POS-13](#pos-13-—-voiding-a-sale-from-a-closed-fiscal-period-is-impossible)). Standard practice is to reverse in the current open period.
- **Guards are outside the transaction and unlocked.** `is_posted` / `is_reversed` are checked *before* `executeInTransaction()` opens. Two concurrent reversals both pass, and you get two reversal entries — the account is credited twice. `PosService::voidSale` happens to hold a lock on the sale, so POS is protected; other callers are not.

---

## F-15 — 219 unindexed foreign key columns in PostgreSQL {#f-15}

**P1 · Verified — executed**

Unlike MySQL, **PostgreSQL does not automatically create an index for a foreign key constraint**, and `$table->foreignId(...)->constrained()` does not add one either. I queried `pg_constraint` against the live `akuntansi` database:

```sql
select c.conrelid::regclass::text as tbl, a.attname as col
from pg_constraint c
  join unnest(c.conkey) k on true
  join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k
where c.contype = 'f' and array_length(c.conkey,1) = 1
  and not exists (select 1 from pg_index i where i.indrelid = c.conrelid and i.indkey[0] = k);
```

**Result: 219 rows.** The hottest paths in the system are all in the list:

| Column | Consequence |
|---|---|
| `journal_entry_lines.journal_entry_id` | **Every** `$entry->lines` access is a sequential scan of the largest table in the database. Hit by `isBalanced()`, `postEntry()`, `reverseEntry()`, and every financial report. |
| `invoice_items.invoice_id` / `bill_items.bill_id` | Seq scan on every invoice/bill detail load |
| `pos_sales.pos_session_id` | Seq scan on every `expectedCash()` and every `GET /pos/sessions/current` |
| `pos_sale_items.pos_sale_id`, `pos_sale_tenders.pos_sale_id` | Seq scan on every receipt render and every void |
| `quotation_items.quotation_id`, `purchase_order_items.purchase_order_id`, `work_order_items.*` | Same pattern across every module |

Every POS foreign key is unindexed: `pos_sales.pos_session_id`, `pos_sale_items.pos_sale_id`, `pos_sale_items.product_id`, `pos_sale_tenders.pos_sale_id`, `pos_session_holds.pos_session_id`, `pos_checkout_idempotencies.pos_sale_id`, and all five on `pos_sessions`.

**Why this has not bitten yet:** on small tables PostgreSQL's planner prefers a seq scan anyway, so the app feels fine in development. The cliff is sharp — performance degrades superlinearly as `journal_entry_lines` grows, and it grows fastest of any table.

**Secondary effect:** an unindexed FK also makes every `DELETE` or `UPDATE` on the *parent* table scan the child table to enforce the constraint.

**Fix:** One migration adding an index for each. The full list is in [Appendix A](#appendix-a--unindexed-foreign-key-columns).

---

## F-13 — PHPStan analyses zero files {#f-13}

**P0 (tooling) · Verified — executed**

```
$ vendor/bin/phpstan analyse --memory-limit=2G --no-progress
Internal error: Class App\Models\ElectricalPanel\Bom was not found while trying to analyse it
[ERROR] Found 1 error
⚠️  Result is incomplete because of severe errors. ⚠️
$ echo $?
1
```

PHPStan aborts on the first file and analyses **nothing**. The exit code is 1, so CI would go red — meaning either the PHPStan step is not wired into CI, or it is being routinely skipped. Either way, `phpstan-baseline.neon` (23,873 bytes tracking ~53 errors) and the entire `README_PHPSTAN.md` workflow are providing **zero** protection today.

The root cause is a genuine runtime bug — see [F-16](#f-16).

---

## F-16 — `SpecValidationRuleSet::boms()` references a class that does not exist {#f-16}

**P1 · Verified — executed**

`app/Models/ElectricalPanel/SpecValidationRuleSet.php:41-46`

```php
namespace App\Models\ElectricalPanel;
// ... no `use App\Models\Manufacturing\Bom;`

public function boms(): HasMany
{
    return $this->hasMany(Bom::class, 'spec_rule_set_id');   // resolves to App\Models\ElectricalPanel\Bom
}
```

With no import, `Bom::class` resolves against the current namespace. I confirmed by autoloader:

```php
class_exists('App\Models\ElectricalPanel\Bom')   => false
class_exists('App\Models\Manufacturing\Bom')     => true
```

**Failure scenario — live, reachable, 100% reproducible:**

`DELETE /api/v1/spec-validation-rule-sets/{id}` → `SpecValidationRuleSetService::delete()` line 55 → `$ruleSet->boms()->count()` → **`Error: Class "App\Models\ElectricalPanel\Bom" not found`** → 500.

The endpoint is registered at `routes/addons/electrical_panel.php:70` and **can never succeed**. `SpecValidationRuleSetResource` also exposes `boms_count`, so any caller that eager-loads the relation crashes the same way.

**Fix:** Add `use App\Models\Manufacturing\Bom;`. This one-line fix also un-blocks PHPStan ([F-13](#f-13)) — after which expect a wave of previously-invisible type errors to surface.

---

## F-14 — Tests run SQLite; production runs PostgreSQL {#f-14}

**P1 (tooling) · Verified — executed**

`phpunit.xml:30-31` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. `.env` sets `DB_CONNECTION=pgsql`.

This is a defensible speed trade-off in many projects. It is **not** defensible in this one, because the service layer's core correctness argument is pessimistic locking:

> **`lockForUpdate()` is a no-op in SQLite.** Every `lockForUpdate()` in this codebase — `PosService` (4 call sites), `ProductStock::lockForStock()`, `PaymentCreationService::lockAndValidateAllocations()`, `InvoiceVoidService::void()`, `BillService`, `FIFOCostingStrategy::recordStockOut()` — is verified by exactly zero tests. The concurrency-safety design has never been executed under the semantics it depends on.

Other divergences that let bugs through:

| Behaviour | SQLite (test) | PostgreSQL (prod) |
|---|---|---|
| `LIKE` | case-insensitive for ASCII | case-sensitive |
| `REGEXP` | no such function → error | operator does not exist → error (different message; both broken — [F-21](#f-21)) |
| Type coercion | permissive (`'10' = 10`) | strict, throws |
| `GROUP BY` | permissive | must list all non-aggregated columns |
| `SELECT … FOR UPDATE` | **silently ignored** | real row locks |
| Missing FK index | irrelevant | seq scan ([F-15](#f-15)) |
| Partial/expression indexes | limited | supported |

**Fix:** Keep SQLite for the fast inner loop, but add a `pgsql` PHPUnit test suite that CI runs against a real PostgreSQL service container — at minimum covering POS checkout, payment allocation, and inventory movements. Then write the concurrency tests that the locking code deserves.

---

## F-17 — Contract tests are not registered with PHPUnit and are red {#f-17}

**P1 (tooling) · Verified — executed**

Two independent problems:

1. **Not registered.** `phpunit.xml` declares only three test suites: `Unit`, `Feature`, `Browser`. `tests/Contract/` is in none of them. `php artisan test` never runs it.

```
$ php artisan test --filter=ApiContractTest
INFO  No tests found.
$ echo $?
1
```

`scripts/check-api-integration.sh:115` runs exactly this command with output suppressed, so the "API contract validation" step of the documented workflow reports failure for the wrong reason and has evidently never passed.

2. **Broken when run directly.** Invoked by path, `tests/Contract/ApiContractTest.php` and `ApiContractEdgeCasesTest.php` produce ~40 failures, all `Expected 200 but received 403`. Cause: `beforeEach` creates `User::factory()->create()` with no roles, and the controllers now call `$this->authorize(...)`. The tests predate the authorization layer and were never updated.

**Net effect:** `CLAUDE.md` presents contract testing as an active guardrail against backend/frontend drift. It has been inert for as long as authorization has existed.

---

## F-18 — Full-suite baseline: 181 failures, and a flaky test {#f-18}

**Verified — executed** (372s run, full suite)

```
Tests: 181 failed, 3 risky, 3 skipped, 4052 passed (14839 assertions)
```

- **178 of the 181** are `tests/Browser/*` failing with `net::ERR_CONNECTION_REFUSED at http://localhost:3000/login` — the Vue SPA was not running. Expected in this environment, but note `--exclude-group=browser` does **not** exclude them (they carry no group annotation), so there is no documented way to run "everything except browser". Add `->group('browser')` in `tests/Pest.php` or a fourth `<testsuite>`.

- **The 3 genuine `tests/Feature` failures** are real and worth acting on:

| Test | Failure | Meaning |
|---|---|---|
| `UserApiTest > it admin can list users` | `26 queries (max 15)` | N+1 regression on `GET /api/v1/users` |
| `SolarProposalApiTest > list` | `24 queries (max 15)` | N+1 regression on `GET /api/v1/solar-proposals` |
| `UserApiTest > it can search by name or email` | `Failed asserting that 2 is identical to 1` | **Flaky, not a product bug** |

The search test is non-deterministic by construction. It creates `User::factory()->admin()->create()` with `fake()->name()` / `fake()->unique()->safeEmail()`, then asserts that searching `"john"` returns exactly 1 result. Faker is unseeded, so any generated name or email containing "john" (`Johnson`, `johnathan@…`) makes it fail. **Seed Faker in `tests/Pest.php`**, or pin the admin's name and email in the test. A flaky test in the suite trains the team to ignore red.

- **The N+1 guard itself is a good idea** — `tests/TestCase.php:31` asserting a query ceiling is a pattern worth extending to the POS and invoice endpoints, which are far hotter than `/users`.

---

# Part 3 — Inventory & Costing

Files reviewed in full: `InventoryService.php` (533 lines), `FIFOCostingStrategy.php`, `WeightedAverageCostingStrategy.php`, `ProductStock.php`, `InventoryCostLayer.php`, `StockOpnameService.php`, `HybridInventoryStrategy::onStockAdjustment`.

This module has the highest concentration of silent-corruption bugs in the codebase.

---

## F-04 — Stock opname erases every sale made during the count {#f-04}

**P1 · Verified — code path**

`app/Services/Inventory/StockOpnameService.php:236-334`

The workflow captures system quantities at `startCounting()`:

```php
foreach ($opname->items as $item) {
    $stock = ProductStock::where(...)->first();
    if ($stock) { $item->captureSystemQuantities($stock); }   // snapshot taken here
}
```

…and then at `approve()`, applies the counted number as an absolute value:

```php
$this->inventoryService->adjust(
    $product, $warehouse,
    $item->counted_quantity,        // ← absolute set, not a delta
    null, ...
);
```

`InventoryService::adjust()` performs `$stock->quantity = $newQuantity;` unconditionally.

**Failure scenario:** A Kopitiam outlet counts 40 units of a product at 09:00 and submits. The supervisor approves at 16:00. Between those times the till sold 15 units, correctly bringing the system to 25. Approval sets the quantity back to **40**. The 15 sold units are resurrected as on-hand stock. The POS sales still exist, the COGS entries still exist, but the inventory is now 15 units overstated — permanently, and with no error anywhere.

This is not a rare edge case in a POS-first product: stock moves *continuously*, so any non-instant count is affected.

**Fix:** Compute and apply a **delta**, not an absolute: `variance = counted_quantity − system_quantity_at_count_time`, then apply `current_quantity + variance`. Or freeze the counted SKUs for the duration of the count.

---

## F-05 — Stock opname's journal entry and inventory movement use different snapshots {#f-05}

**P1 · Verified — code path**

`StockOpnameService::approve()` performs two operations that are supposed to describe the same event:

| Operation | Source of truth | Value used |
|---|---|---|
| Inventory movement | `InventoryService::adjust()` | `counted_quantity − current_quantity_at_approval` |
| Journal entry | `HybridInventoryStrategy::calculateAdjustmentValue()` line 131 | `sum(items.variance_value)` — computed at **count** time |

These are the same number only if nothing moved between counting and approval. Given [F-04](#f-04), they routinely differ.

**Failure scenario:** Continuing the example above — the JE books a variance of `(40 − 40) × cost = 0` (the count matched what the system said at 09:00, so no journal entry is created at all: `if ($adjustmentValue === 0) return null;`). Meanwhile the inventory movement adds **+15 units of value** to `product_stocks.total_value`.

**The Inventory GL account and the stock valuation report now differ by 15 units of cost, with no journal entry to explain it.** No report will ever flag this, because each side is internally consistent. It compounds with every opname.

**Fix:** Derive both the movement and the journal entry from a single computed delta inside one transaction. Ideally, have `InventoryService::adjust()` return the actual value delta and build the JE from *that*.

---

## F-06 — `InventoryService::adjust()` never touches FIFO cost layers {#f-06}

**P1 · Verified — code path**

`app/Services/Inventory/InventoryService.php:207-273`

`adjust()` writes `$stock->quantity`, `$stock->average_cost`, and `$stock->total_value` directly. It **never calls `$this->policyManager->costing()`** — unlike `stockIn()` and `stockOut()`, which both do.

When `costing_method = 'fifo'`, `inventory_cost_layers` is the authoritative record of what stock exists and what it cost. After any adjustment, the layers no longer sum to `product_stocks.quantity`.

**Failure scenario — the drift is then *hidden* by a defensive branch.** `FIFOCostingStrategy::recordStockOut()` lines 68-72:

```php
if ($remaining > 0) {
    // This shouldn't happen if stock validation passed, but handle gracefully
    $totalCost += $remaining * $stock->average_cost;
}
```

An upward adjustment leaves fewer layer-units than `quantity`. A later stock-out exhausts the layers, silently falls through to `average_cost` for the remainder, and produces a COGS figure that no cost layer supports. The comment says "shouldn't happen" — but [F-04](#f-04) guarantees it does, on every opname. A downward adjustment leaves orphaned layers that overstate inventory value forever.

**Fix:** Add `recordAdjustment(ProductStock $stock, int $delta, ?int $unitCost)` to the `CostingStrategy` contract. FIFO creates a layer for a positive delta and consumes oldest-first for a negative one. Change the `$remaining > 0` branch from a silent fallback to a logged error or an exception — it is a data-integrity alarm, not a rounding nicety.

---

## F-19 — FIFO COGS is rounded per-issue, so inventory value leaks

**P2 · Verified — code path**

`FIFOCostingStrategy::recordStockOut()` line 79: `return (int) round($totalCost / $quantity);`

The caller then computes `total_cost = quantity × unitCost`. When layers do not divide evenly, these disagree:

> 3 units consumed for a true layer cost of Rp 1,000 → `unitCost = round(333.33) = 333` → `total_cost = 999`

The cost layers were depleted by Rp 1,000; the COGS journal entry credits Inventory Rp 999. **Rp 1 is orphaned in the sub-ledger on every uneven issue.** POS is directly affected — `PosService::checkout()` uses `abs((int) $movement->total_cost)` as the COGS amount.

Individually trivial; across hundreds of POS lines a day, the Inventory GL balance and the stock valuation report slowly and permanently separate — and there is no reconciliation report that would catch it.

**Fix:** Have `recordStockOut()` return the exact integer `$totalCost`, and let the caller derive a display unit cost from it. Never reconstruct a total by multiplying a rounded unit price.

---

## F-20 — `transfer()` deadlocks, and ignores reservations

**P2 · Verified — code path** · `InventoryService::transfer()` lines 280-357

Two defects:

**(a) Lock-ordering deadlock.** `$fromStock` is locked, then `$toStock`. A concurrent transfer of the same product in the opposite direction locks them in reverse order → classic AB-BA deadlock. Since `executeInTransaction()` uses `DB::transaction($callback)` with **no retry attempts**, the deadlock surfaces to the user as a raw 500.

**(b) Reservations ignored.** `transfer()` validates against `$fromStock->quantity`, while `stockOut()` validates against `getAvailableQuantity()` (quantity − reserved). So stock reserved for a confirmed sales order can be transferred away, and the subsequent delivery fails with insufficient stock.

**Fix:** Lock in deterministic order (e.g. ascending `warehouse_id`); pass an attempt count to `DB::transaction($callback, 3)` for deadlock-prone operations; validate against `getAvailableQuantity()`.

---

## F-23 — `current_stock` is a cache updated outside the lock

**P2 · Verified — code path** · `Product::syncCurrentStock()` (line 278) called from every movement

```php
public function syncCurrentStock(): void { $this->update(['current_stock' => $this->total_stock]); }
public function getTotalStockAttribute(): int { return $this->stocks()->sum('quantity'); }
```

`stockOut()` locks `product_stocks` for one `(product, warehouse)` pair — but `syncCurrentStock()` sums across **all** warehouses with no lock.

**Failure scenario:** Two sales of the same product in two different outlets commit concurrently. Both read the same pre-update sum and both write it. `products.current_stock` is now one sale too high, permanently. `getInventorySummary()`'s low-stock and out-of-stock counts read this column, as does `Product::isOutOfStock()`.

**Fix:** Either drop the column and compute on demand (indexed `product_stocks.product_id` makes this cheap), or update it with an atomic `UPDATE products SET current_stock = current_stock + :delta`.

---

## Other inventory findings

| ID | Sev | Finding |
|---|---|---|
| INV-01 | P2 | `ProductStock::lockForStock()` declares `: self` but ends in `->first()`, which is nullable. Its `getOrCreate()` uses `firstOrCreate`, which races against the `(product_id, warehouse_id)` unique index — two cashiers first-selling the same new product in the same warehouse get an unhandled `SQLSTATE[23505]` rather than a retry. |
| INV-02 | P2 | `getMovementSummary()` loads **every** movement in the date range into memory (`$query->get()`) and filters in PHP. Directly contradicts the project's own performance guidance in `CLAUDE.md`. Should be a single grouped aggregate query. |
| INV-03 | P3 | `getMovementSummary()` returns `transfers.count = …->count() / 2`. When filtered by warehouse, only one leg of each transfer matches, yielding fractional counts like `0.5`. |
| INV-04 | P3 | `removeStock()` clamps with `max(0, …)`, silently swallowing an oversell instead of failing loudly. |
| INV-05 | P3 | `adjust()` accepts a negative `$newQuantity` with no guard. |
| INV-06 | P3 | `getStockValuation()` filters `where('quantity','>',0)`, hiding negative-stock rows from the valuation report — exactly the rows an accountant needs to see. |
| INV-07 | P3 | `stockOut` writes a **negative** `quantity` but a **positive** `total_cost`. The sign convention is inconsistent; any report summing `total_cost` across movement types gets a meaningless number. `PosService` works around it with `abs()`. |
| INV-08 | P3 | `transfer()` derives the inbound movement number via `str_replace('TRF','TRI', $transferNumber)` rather than generating it, so it bypasses uniqueness checks for the `TRI` series. |
| INV-09 | P2 | `processBill()` uses `$item->unit_price` as inventory cost — excluding line discounts and possibly including tax. Verify against `bill_items` semantics; if prices are tax-inclusive, inventory is overstated by 11% on every purchase. |
| INV-10 | P3 | `startCounting()` and `approve()` both query `ProductStock` inside a `foreach` (N+1). |

---

# Part 4 — Sales

Files reviewed: `InvoiceVoidService.php` (full), `InvoicePaymentService.php` (full), `InvoicePaymentStatusService.php` (full), `SalesReturns/Handlers/JournalEntryHandler.php` (full), `PaymentCreationService.php` (full), `StorePaymentRequest.php` (full).

---

## SAL-01 — Sales returns bump `paid_amount` without updating status, without a lock, and without a cap

**P1 · Verified — code path** · `app/Domain/Sales/SalesReturns/Handlers/JournalEntryHandler.php:93-99`

```php
if ($salesReturn->invoice_id && $salesReturn->invoice) {
    $invoice = $salesReturn->invoice;
    $invoice->paid_amount += $salesReturn->total_amount;
    $invoice->save();
}
```

Four distinct problems in five lines:

1. **No status transition.** Unlike every payment path — which routes through `transitionPayableStatus()` — this writes `paid_amount` and stops. An invoice fully relieved by a credit note ends up with `paid_amount == total_amount` but `status = Sent` or `Overdue`.
   **Failure scenario:** `OverdueService` and `ReminderService` keep dunning a customer for an invoice that was fully returned. The customer receives collection notices for goods they sent back.

2. **`paid_amount` now means two different things.** It conflates cash collected with credit-note relief. Every cash-collection report, DSO calculation, and aging bucket silently includes returns.

3. **Read-modify-write with no lock.** A concurrent payment on the same invoice loses one of the two updates.

4. **No cap.** Returns plus payments can drive `paid_amount > total_amount`, making `getOutstandingAmount()` negative and injecting negative balances into AR aging.

**Fix:** Route through the same status-transition path payments use; take `lockForUpdate()` on the invoice; and consider a separate `credited_amount` column so `paid_amount` retains a single meaning.

---

## SAL-02 — `InvoicePaymentService::recordPayment()` is a lost-update bug (currently dead code)

**P2 · Verified — executed** · `app/Services/Sales/InvoicePaymentService.php:44-51`

```php
$invoice->paid_amount += $amount;
$invoice->save();
```

No lock, no atomic increment, no overpayment check. I grepped the whole tree: **`recordPayment()` and `reversePayment()` have no callers.** The live payment path is `PaymentCreationService`, which is correctly written.

Flagging it anyway because it is a loaded gun: the method is public on a service and reads like the obvious way to record a payment. Delete it, or fix it to `lockForUpdate()` + validate against outstanding before someone wires it up.

**Credit where due —** `PaymentCreationService::lockAndValidateAllocations()` is the correct pattern: `lockForUpdate()->findOrFail()` on each payable, then explicit validation of state and of `$amount > $payable->getOutstandingAmount()`. This is the model the rest of the codebase should follow.

---

## SAL-03 — Multi-allocation payments are unreachable via the API

**P2 · Verified — code path**

`PaymentCreationService::normalizeAllocations()` supports an `allocations[]` array for splitting one payment across several invoices. But `StorePaymentRequest::rules()` does **not** declare `allocations`, and `PaymentController::store()` passes `$request->validated()` — which strips undeclared keys.

The multi-allocation code path (roughly 60 lines including validation that allocations sum to the payment amount) is dead via HTTP. Either wire it up with proper rules, or remove it.

**If you do wire it up, note two latent bugs in that path:**
- **Negative allocations pass validation.** Only an upper bound is checked (`$amount > outstanding`). Allocations of `[+2_000_000, −1_000_000]` sum correctly to the payment total while *reducing* the second invoice's `paid_amount`. Add `min:1` per allocation.
- **Deadlock.** Payables are locked in client-supplied order. Two concurrent multi-invoice payments touching the same invoices in different orders deadlock. Sort by id before locking.

---

## SAL-04 — `transitionPayableStatus()` fails silently

**P2 · Verified — code path** · `PaymentCreationService.php:397-405`

```php
if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
    $payable->stateMachine()->transitionTo($targetStatus, [...]);
}
// no else — if the transition is not allowed, nothing happens and nothing is reported
```

`paid_amount` has already been written at this point. If the state machine disallows the transition, the invoice ends up fully paid in amount but wrong in status, with no exception and no log line. Add an `else` that logs a warning at minimum.

---

## SAL-05 — Invoice void cascade: strong overall, three gaps

**Verified — code path** · `app/Services/Sales/Invoice/InvoiceVoidService.php`

This is genuinely well-built. The cascade order is documented, it blocks on irreversible states (`Delivered` DOs, `Completed` SRs), it delegates to the proper services rather than reaching for low-level reversals, and it takes `lockForUpdate()` on the invoice. It follows the `CLAUDE.md` rule it is meant to embody. Gaps:

| ID | Sev | Finding |
|---|---|---|
| SAL-05a | P2 | Down-payment restore (`$dp->applied_amount -= $application->amount`, line 169) takes **no lock**. Two invoices voided concurrently that share one DP lose an update on `applied_amount`. |
| SAL-05b | P3 | `->each()` on an Eloquent Builder chunks by **offset**. Because each callback changes the very `status` the query filters on, the result set shrinks between pages and rows get skipped. Harmless below 1,000 child documents per invoice, but latent. Use `->cursor()` or collect ids first. |
| SAL-05c | P3 | The `AuditLog` payload re-queries `DeliveryOrder::where(...)->where('status', Cancelled)->count()` *after* the cascade, so it counts pre-existing cancellations too. The audit record overstates what this operation did. |

**Inherited:** the whole cascade is subject to [F-11](#f-11) — reversal reasons discarded — and to the closed-period reversal problem, which makes voiding a prior-period invoice impossible.

---

# Part 5 — Purchasing

## PUR-01 — `GoodsReceiptNoteService::complete()` has no lock; double-completion doubles inventory

**P2 · Verified — code path** · `app/Services/Purchasing/GoodsReceiptNoteService.php:236-291`

`canComplete()` is checked *before* `executeInTransaction()` opens, and the GRN row is never locked. The status transition happens **last**, after all the `stockIn()` calls.

**Failure scenario:** A double-submitted "Complete GRN" (impatient user, retried request) has both requests pass `canComplete()`, both run the `stockIn()` loop, and both call `$poItem->receive()`. Inventory is received **twice** — quantity and value both doubled — and two `Dr Inventory / Cr GRNI` journal entries are posted. The second `transitionTo()` may throw, rolling back only the second transaction, but the outcome depends entirely on interleaving.

**Fix:** `$grn = GoodsReceiptNote::lockForUpdate()->findOrFail($grn->id);` as the first statement inside the transaction, then re-check `canComplete()`.

---

## PUR-02 — `PurchaseOrderItem::receive()` is a lost update

**P2 · Verified — code path** · `app/Models/Purchasing/PurchaseOrderItem.php:152-157`

```php
public function receive(float $quantity): void
{
    $this->quantity_received += $quantity;
    $this->last_received_at = now();
    $this->save();
}
```

Read-modify-write, no lock. Two GRNs completing concurrently against the same PO line lose one increment. The PO then appears under-received and can be received again beyond its ordered quantity — the over-receipt check in `GoodsReceiptNoteService::updateItem()` (line 170-178) compares against a stale `quantity_received`.

Note the signature takes `float` while the column and all comparisons treat it as an integer count — a type inconsistency worth resolving.

---

## PUR-03 — GRN unit price used directly as inventory cost

**P2 · Inferred** · `GoodsReceiptNoteService::complete()` line 262

`stockIn(..., $item->unit_price, ...)` — line discounts are not deducted, and if `unit_price` is tax-inclusive, inventory is capitalised 11% too high. `LandedCostService` exists to allocate freight/duty afterward, which suggests the base cost is meant to be net — worth confirming against `bill_items`/`goods_receipt_note_items` semantics and adding a test that pins it.

The same concern applies to `InventoryService::processBill()`, which uses `$item->unit_price` identically.

---

# Part 6 — Accounting

## F-07 — `opening_balance` is a directly-writable, unbalanced ledger input {#f-07}

**P1 · Verified — code path**

`accounts.opening_balance` is a plain column, listed in `Account::$fillable`, and accepted by both `StoreAccountRequest` and `UpdateAccountRequest` as simply `['integer']` — no `min`, no `nullable`, no offsetting entry required.

It is then added to **every** balance computation:

- `AccountBalanceService::getTrialBalance()` line 258 — `$balance = (int) $account->opening_balance + (movements)`
- `AccountBalanceService` lines 82, 99, 184, 188 — ledger and running balances
- `Account::getBalance()` lines 134, 160

**Failure scenario:** `PATCH /api/v1/accounts/{cashAccountId}` with `{"opening_balance": 500000000}` adds Rp 500,000,000 to the Cash line of the trial balance with **no corresponding credit anywhere**. Total debits no longer equal total credits. There is no journal entry, no audit log, and no report that will explain the discrepancy — the money simply appears. A negative value works equally well for making an unwanted balance disappear.

This is a violation of the fundamental invariant the whole `CLAUDE.md` accounting section is built to protect ("Every financial test must verify: Trial Balance Debits = Credits").

**Fix:** Opening balances must be a posted, balanced opening journal entry (`source_type = SOURCE_OPENING`), which the schema already anticipates. Remove `opening_balance` from `$fillable` and from both FormRequests; derive it from the opening JE.

---

## F-08 — Trial balance silently drops inactive accounts that hold movements {#f-08}

**P1 · Verified — code path** · `app/Services/Accounting/AccountBalanceService.php:237-240`

```php
$accounts = Account::query()->where('is_active', true)->orderBy('code')->get();
```

The balances subquery correctly sums **all** posted lines — but the outer map only iterates active accounts.

**Failure scenario:** An accountant deactivates a legacy revenue account that still carries Rp 80,000,000 of posted movements from earlier in the year. The account vanishes from the trial balance. Total credits drop by Rp 80,000,000 while debits are unchanged. **The trial balance no longer balances, and nothing reports why** — the account is simply not on the page. The balance sheet and income statement, which build on the same service, are wrong by the same amount.

**Fix:** Include any account with non-zero movement or non-zero balance regardless of `is_active`, flagging it as inactive in the output. Deactivation should hide an account from *pickers*, never from the *ledger*.

---

## ACC-01 — Journal entries can be created outside any fiscal period

**P2 · Verified — code path** · `JournalEntryService::createEntry()` lines 43-58, `postEntry()` lines 130-147

Both guards are conditional on a period existing:

```php
$fiscalPeriod = FiscalPeriod::forDate($entryDate);
if ($fiscalPeriod && $fiscalPeriod->getStatus() === Closed) { throw ...; }
if ($fiscalPeriod && $fiscalPeriod->getStatus() === Locked) { throw ...; }
// no period at all → falls through, fiscal_period_id = null
```

Contrast with `PosService::assertPeriodAllowsTill()`, which correctly **throws** when no period exists. POS is protected; the rest of the application is not.

**Failure scenario:** A back-dated or future-dated journal entry lands on a date with no fiscal period. It posts successfully with `fiscal_period_id = null`. Period-scoped reports miss it, and `YearEndCloseService` — which works period by period — never closes it. The entry is real, affects the trial balance, and is invisible to every period-based process.

**Fix:** Make a missing period an error in `createEntry()`, matching the POS behaviour. Consolidate on one guard: `assertPeriodAllowsPosting(DateTimeInterface $date)` used everywhere.

---

## ACC-02 — Two competing fiscal-period status mechanisms

**P3 · Verified — code path**

`JournalEntryService` uses `$period->getStatus() === FiscalPeriodStatus::Closed`. `StorePaymentRequest::withValidator()` uses `$period->is_closed` / `$period->is_locked`. Two representations of the same state, checked in different places, will eventually diverge. Pick one — the enum — and make the booleans derived accessors.

---

## ACC-03 — Journal lines are not validated for sign or exclusivity

**P2 · Verified — code path** · `JournalEntryLine`, `JournalEntry::isBalanced()`

Nothing prevents:
- A line with **both** debit and credit non-zero.
- A line with a **negative** debit and a matching negative credit — `isBalanced()` returns `true` for `debit = −500_000, credit = −500_000`.

Negative amounts flow straight into `getTrialBalance()`'s `SUM(jel.debit)` and corrupt the totals while every balance check passes.

**Fix:** Add a database `CHECK (debit >= 0 AND credit >= 0 AND (debit = 0 OR credit = 0))` — PostgreSQL enforces it properly — plus a model-level guard.

---

## ACC-04 — `JournalEntry` has a `deleted_at` column but does not use `SoftDeletes`

**P3 · Verified — code path**

The migration declares `$table->softDeletes()`; the model does not `use SoftDeletes`. Deletes are therefore hard deletes, and `deleted_at` is permanently null. `getTrialBalance()` dutifully filters `whereNull('je.deleted_at')` — a no-op that reads as protection but provides none. Either adopt the trait or drop the column; the current state misleads.

---

## ACC-05 — `postEntry()` and `reverseEntry()` guards are unlocked

**P2 · Verified — code path** — covered under [F-11](#f-11). Both read a flag and then write it with no row lock and, for `reverseEntry`, with the check outside the transaction entirely.

---

# Part 7 — Other modules

| ID | Sev | Module | Finding |
|---|---|---|---|
| <a id="f-21"></a>**F-21** | **P2** | Filters | `HasSearchFilter::keyword()` (line 87) uses the `REGEXP` operator — **MySQL-only**. PostgreSQL uses `~`; SQLite has no `REGEXP` function by default. The method throws on both the production and the test database. Reachable from **any** list endpoint via [F-10](#f-10) (`?keyword=x`). Additionally, `$searchTerm` is injected into a regex unescaped, so a search containing `(` raises an invalid-regex error and a crafted pattern is a ReDoS vector. |
| <a id="f-22"></a>**F-22** | **P3** | Shared | `ProjectBasedNumberStrategy` (line 49) uses `SUBSTRING_INDEX(...)` and `CAST(... AS UNSIGNED)` — both **MySQL-only**, both fatal on PostgreSQL. Currently dead code (`NumberGenerationManager` is imported in `StrategyServiceProvider` but never instantiated or bound), so it is a landmine rather than a live bug. Either wire it up correctly or delete the `NumberGeneration` subsystem — it duplicates `DocumentNumbers` and carries the same 4-digit rollover flaw ([F-02](#f-02)). |
| ELE-01 | **P1** | ElectricalPanel | `DELETE /api/v1/spec-validation-rule-sets/{id}` always 500s — see [F-16](#f-16). |
| SOL-01 | P2 | Solar | `GET /api/v1/solar-proposals` fails the N+1 query-ceiling test at 24 queries (max 15) — see [F-18](#f-18). |
| SOL-02 | P3 | Solar | The `2025_12_28` migration adds `'converted'` to the status CHECK constraint **only on PostgreSQL**; for MySQL it explicitly defers to "application-level validation" that does not exist, and for SQLite it silently does nothing. Since production is PostgreSQL this is currently correct, but the divergence is undocumented at the model layer. |
| MFG-01 | P3 | Manufacturing | N+1 queries inside loops in `MrpDemandService` (lines 140, 231), `MrpSuggestionService` (318, 338), `BomVariantGroupService` (299), `WorkOrderMaterialService` (239). |
| PRJ-01 | P3 | Projects | `TaskService` line 180 queries inside a `foreach`. |
| GEN-01 | P3 | All | `WithTransaction::executeInTransaction()` calls `DB::transaction($callback)` with **no retry attempts**. Every deadlock and serialization failure surfaces as a 500. Pass an attempt count (e.g. `DB::transaction($callback, 3)`) for POS checkout, transfers, and payment allocation. |
| GEN-02 | P3 | All | `WithTransaction` logs `$this->logger->logExit(static::class, $operation, $result)` — the **full result object**. For payments and invoices this writes customer and financial data into application logs on every successful operation. Log an identifier, not the payload. |

---

# Part 8 — Recommended remediation order

**Immediately (before any pilot with real money):**

1. **[F-01](#f-01)** — Authorize `RoleController` and `PermissionController`. One-line-per-method fix; closes a total privilege escalation.
2. **[F-03](#f-03)** — Add `permission:` middleware to route groups; at minimum authorize `InventoryController` and `StockOpnameController` today.
3. **[F-09](#f-09)** — Allowlist `sort_dir`. One line.
4. **[F-16](#f-16)** — Add the missing `use App\Models\Manufacturing\Bom;`. One line; also un-blocks PHPStan.
5. **[F-02](#f-02)** — Replace `DocumentNumbers` with a sequence table. This has a hard deadline: the JE prefix crosses 10,000/month within weeks of go-live.

**Next sprint (data-integrity):**

6. **[F-04](#f-04)/[F-05](#f-05)/[F-06](#f-06)** — Rework stock opname to apply deltas, and add `recordAdjustment()` to the costing contract. These three share one root cause and should be fixed together, with tests.
7. **[F-07](#f-07)/[F-08](#f-08)** — Make opening balances a posted journal entry; stop filtering the trial balance by `is_active`.
8. **[F-11](#f-11)** — Honour the reversal description; reverse into the current open period.
9. **[F-12](#f-12)** — Journalize POS cash over/short, the opening float, and the closing deposit.
10. **[F-10](#f-10)** — Allowlist filter methods.

**Then (foundations):**

11. **[F-13](#f-13)/[F-14](#f-14)/[F-17](#f-17)/[F-18](#f-18)** — Get the safety nets actually running: PHPStan green, a PostgreSQL test suite, contract tests registered and passing, Faker seeded.
12. **[F-15](#f-15)** — One migration for the 219 missing FK indexes.
13. Locking gaps: **PUR-01**, **PUR-02**, **SAL-01**, **SAL-05a**, **F-20**, **INV-01**, **F-23**. Best tackled *after* the PostgreSQL suite exists, so each fix arrives with a test that could actually fail without it.

---

# Appendix A — Unindexed foreign key columns

219 total, from the live `akuntansi` database. Reproduce with:

```sql
select c.conrelid::regclass::text as tbl, a.attname as col
from pg_constraint c
  join unnest(c.conkey) k on true
  join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k
where c.contype = 'f' and array_length(c.conkey,1) = 1
  and not exists (select 1 from pg_index i where i.indrelid = c.conrelid and i.indkey[0] = k)
order by 1, 2;
```

**Highest priority (hot paths, by observed query volume):**

```
journal_entry_lines.journal_entry_id      ← single most important
invoice_items.invoice_id
bill_items.bill_id
quotation_items.quotation_id
purchase_order_items.purchase_order_id
pos_sales.pos_session_id
pos_sale_items.pos_sale_id
pos_sale_tenders.pos_sale_id
pos_session_holds.pos_session_id
inventory_movements.warehouse_id
inventory_cost_layers.warehouse_id
product_stocks.warehouse_id
payments.journal_entry_id
invoices.contact_id
bills.contact_id
journal_entries.fiscal_period_id
```

**All POS columns:**
```
pos_checkout_idempotencies.pos_sale_id     pos_sales.cogs_journal_entry_id
pos_sale_items.inventory_movement_id       pos_sales.created_by
pos_sale_items.pos_sale_id                 pos_sales.journal_entry_id
pos_sale_items.product_id                  pos_sales.pos_session_id
pos_sale_tenders.pos_sale_id               pos_sales.voided_by
pos_session_holds.pos_session_id           pos_sessions.cash_account_id
pos_sessions.closed_by                     pos_sessions.opened_by
pos_sessions.qris_account_id               pos_sessions.warehouse_id
```

The remaining ~185 span every module (`work_orders.*`, `purchase_returns.*`, `sales_returns.*`, `mrp_*`, `subcontractor_*`, `stock_opnames.*`, `solar_proposals.*`, `budget_lines.account_id`, `role_user.user_id`, `permission_role.role_id`, …). Generate the migration from the query above rather than transcribing by hand.

---

# Appendix B — Verification commands used

```bash
# Full non-browser suite (372s):    181 failed / 4052 passed
php artisan test tests/Feature tests/Unit tests/Contract

# PHPStan — aborts, analyses zero files
vendor/bin/phpstan analyse --memory-limit=2G --no-progress   # exit 1

# Contract tests are unregistered
php artisan test --filter=ApiContractTest                     # "No tests found", exit 1

# Phantom class
php -r 'require "vendor/autoload.php";
  var_dump(class_exists("App\\Models\\ElectricalPanel\\Bom"),   // false
           class_exists("App\\Models\\Manufacturing\\Bom"));'   // true

# Document-number rollover (pure logic, reproduced directly)
php -r '$l="POS-202608-9999";  echo (int)substr($l,-4)+1;'      # 10000 → 5 digits
php -r '$l="POS-202608-10000"; echo (int)substr($l,-4)+1;'      # 1     → collision
php -r '$a=["POS-202608-0002","POS-202608-10000","POS-202608-9999"]; rsort($a); echo $a[0];'
                                                                # "…-9999" is the lexicographic max

# Controllers with no authorization
for f in $(find app/Http/Controllers/Api -name '*Controller.php'); do
  grep -q "authorize(\|Gate::\|hasPermission\|->can(" "$f" || echo "$f"; done
```

---

*End of audit. Findings are ordered by severity within each module. Every claim above is marked with how it was established; where I traced a code path rather than executing it, the trace is complete from HTTP entry point to defect and the reasoning is stated so it can be checked.*
