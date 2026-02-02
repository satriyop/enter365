# API Contract Mismatches Report

**Generated:** 2026-01-25  
**Purpose:** Review of mismatches between Laravel API Resources and OpenAPI schema (api.json)

## Summary

- **Total Resources Checked:** 70
- **Resources with Mismatches:** 42
- **Critical Mismatches:** 3 (affecting frontend consumption)

---

## 🔴 Critical Mismatches (Breaking Frontend)

### 1. QuotationResource: `total_amount` vs `total`

**Issue:** Backend returns `total_amount` but OpenAPI schema defines `total`

**Backend (QuotationResource.php):**
```php
'total_amount' => $this->total_amount,
```

**OpenAPI Schema (api.json):**
```json
"total": {
    "type": "integer"
}
```

**Frontend Usage:**
- `src/pages/quotations/QuotationDetailPage.vue` uses `quotation.total_amount`
- `src/pages/quotations/QuotationListPage.vue` uses `quotation.total_amount`
- Generated types from `api.json` expect `total`

**Impact:** ⚠️ **HIGH** - Frontend will fail at runtime when accessing `total_amount` if types are strictly enforced, or will have incorrect types.

**Fix Options:**
1. **Recommended:** Change QuotationResource to return `total` (consistent with InvoiceResource)
2. Alternative: Update OpenAPI schema to use `total_amount` (but breaks consistency)

---

## 🟡 Medium Priority Mismatches

### 2. InvoiceResource: Permission Fields Structure

**Issue:** Resource returns `actions` object, but individual `can_*` fields are also expected

**Backend (InvoiceResource.php):**
```php
'actions' => [
    'can_edit' => $stateMachine->canEdit(),
    'can_post' => $stateMachine->canPost(),
    // ...
],
```

**OpenAPI Schema:** Has `actions` object correctly defined

**Note:** This appears correct in schema, but script detected individual fields. May be false positive.

---

### 3. LabelValueResource: Missing `value` and `label`

**Issue:** Resource returns `value` and `label` but schema doesn't define them

**Impact:** Medium - Used in many resources as nested objects

---

## 📊 All Mismatches by Resource

### Resources Missing Fields in Schema (Backend has, Schema doesn't):

1. **AttachmentResource**: `name`
2. **BomItemResource**: `name`, `sku`
3. **BomResource**: `sku`
4. **BomTemplateItemResource**: `code`, `name`, `category`, `sku`, `purchase_price`
5. **BomTemplateResource**: `material_count`, `labor_count`, `overhead_count`
6. **BomVariantGroupResource**: Multiple fields (see full report)
7. **BudgetLineResource**: `code`, `name`, `type`
8. **BudgetResource**: `start_date`, `end_date`
9. **ComponentBrandMappingResource**: `name`, `sku`, `purchase_price`, `selling_price`, `current_stock`
10. **DeliveryOrderItemResource**: `name`, `sku`
11. **DeliveryOrderResource**: `invoice_number`, `total_amount`, `name`, `address`, `phone`
12. **DownPaymentApplicationResource**: `name`
13. **DownPaymentResource**: `name`, `email`
14. **InvoiceResource**: `can_edit`, `can_post`, `can_cancel`, `can_delete`, `can_mark_as_paid`, `can_mark_as_partial`, `self`
15. **LabelValueResource**: `value`, `label` ⚠️ **CRITICAL** - Used everywhere
16. **QuotationResource**: `total_amount` ⚠️ **CRITICAL**
17. **QuotationVariantOptionResource**: `bom_number`, `name`, `variant_name`, `variant_label`, `total_cost`, `unit_cost`
18. **SalesReturnItemResource**: `name`
19. **SubcontractorInvoiceResource**: `name`, `sc_wo_number`
20. **SubcontractorWorkOrderResource**: `phone`, `email`, `wo_number`, `project_number`
21. **UserResource**: `display_name`
22. **WorkOrderItemResource**: (see full report)

### Resources Missing Fields in Backend (Schema has, Backend doesn't):

1. **BomVariantGroupResource**: `created_at`, `updated_at`
2. **QuotationResource**: `total` (should be `total_amount`)

---

## 🔧 Recommended Fix Strategy

### Phase 1: Critical Fixes (Do First)

1. **Fix QuotationResource `total` vs `total_amount`**
   - Change QuotationResource to return `total` instead of `total_amount`
   - Update frontend to use `total`
   - Regenerate OpenAPI schema

2. **Fix LabelValueResource**
   - Ensure schema includes `value` and `label` fields
   - These are used in many nested resources

### Phase 2: Consistency Fixes

3. **Standardize field naming**
   - Review all resources for consistent naming
   - Consider: `total_amount` vs `total`, `display_name` vs `name`, etc.

4. **Add missing fields to schema**
   - Run Scramble with proper annotations
   - Add PHPDoc comments to Resource classes
   - Regenerate api.json

### Phase 3: Validation

5. **Add automated checks**
   - Run this mismatch checker in CI/CD
   - Fail builds if mismatches detected

---

## 📝 Notes

- **Scramble Configuration:** Scramble should automatically detect fields from Resources, but may miss:
  - Conditional fields (`whenLoaded`, `when`)
  - Computed fields
  - Nested resource fields

- **Frontend Impact:** 
  - TypeScript types are generated from `api.json`
  - Runtime errors may occur if backend returns fields not in schema
  - Type safety is compromised

---

## 🛠️ How to Fix

1. **For each mismatch:**
   ```bash
   # 1. Fix the Resource or Schema
   # 2. Regenerate OpenAPI spec
   php artisan scramble:export --path=api.json
   
   # 3. Regenerate frontend types
   cd ../front-end-enter365
   npm run types:generate
   ```

2. **Add PHPDoc annotations to Resources:**
   ```php
   /**
    * @property int $total_amount The total amount in base currency
    */
   ```

3. **Use Scramble attributes:**
   ```php
   use Dedoc\Scramble\Support\Generator\OpenApi;
   
   // In Resource class
   ```

---

## 🔍 Verification

After fixes, run:
```bash
php check-api-mismatches.php
```

Should show: `✅ No mismatches found!`
