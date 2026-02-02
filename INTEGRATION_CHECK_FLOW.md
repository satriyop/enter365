# Backend-Frontend Integration Check Flow

This document outlines the systematic process for verifying API contract consistency between Laravel backend and Vue.js frontend.

## Overview

**Goal:** Ensure API Resources match OpenAPI schema (api.json) and frontend TypeScript types are correctly generated.

**Tools Used:**
- Custom PHP script for mismatch detection
- PHPStan for type checking
- Scramble for OpenAPI generation
- Manual verification

---

## Step-by-Step Flow

### Step 1: Generate/Update OpenAPI Schema

**Action:** Ensure the OpenAPI schema is up-to-date

```bash
cd /Users/satriyo/dev/laravel-project/enter365
php artisan scramble:export --path=api.json
```

**Why:** The schema is the source of truth. Frontend types are generated from this file.

**Check:** Verify `api.json` exists and was recently updated.

---

### Step 2: Run Mismatch Detection Script

**Action:** Compare API Resources with OpenAPI schema

```bash
php check-api-mismatches.php
```

**What it does:**
- Scans all `*Resource.php` files in `app/Http/Resources/Api/V1/`
- Extracts fields from `toArray()` methods
- Compares with OpenAPI schema definitions
- Reports mismatches

**Output:** Lists resources with:
- Fields in Resource but NOT in Schema
- Fields in Schema but NOT in Resource

**Script Location:** `/Users/satriyo/dev/laravel-project/enter365/check-api-mismatches.php`

---

### Step 3: Identify Critical Mismatches

**Focus on:**
1. **Field name mismatches** (e.g., `total` vs `total_amount`)
2. **Missing required fields** in schema
3. **Type inconsistencies** (int vs string, etc.)
4. **Resources used by frontend** (prioritize these)

**Example from our check:**
- ❌ QuotationResource: Returns `total_amount` but schema had `total`
- ❌ PurchaseOrderResource: Returns `total` but should be `total_amount`

**Decision:** Determine the "correct" field name based on:
- Business intent
- Consistency with other resources
- Existing frontend usage
- Database column names

---

### Step 4: Fix Backend Resources

**Action:** Update API Resources to match the chosen standard

**Example Fix:**
```php
// Before
'total' => $this->total_amount,

// After (standardized to total_amount)
'total_amount' => $this->total_amount,
```

**Files to update:**
- `app/Http/Resources/Api/V1/{Resource}Resource.php`
- Update PHPDoc `@return array{...}` to match implementation

**Check:** Verify PHPDoc types match implementation

---

### Step 5: Update Tests

**Action:** Update test assertions to match new field names

**Locations:**
- `tests/Feature/Api/V1/{Resource}ApiTest.php`

**Example:**
```php
// Before
->assertJsonPath('data.total', 3885000);

// After
->assertJsonPath('data.total_amount', 3885000);
```

**Run tests:**
```bash
php artisan test --filter={Resource}ApiTest
```

**Verify:** All tests pass

---

### Step 6: Update Frontend (if needed)

**Action:** Update frontend to use correct field names

**Locations:**
- `src/pages/{resource}/{Resource}ListPage.vue`
- `src/pages/{resource}/{Resource}DetailPage.vue`
- `src/api/use{Resource}.ts`

**Example:**
```typescript
// Before
quotation.total

// After
quotation.total_amount
```

**Note:** Frontend types are auto-generated from `api.json`, so after regenerating the schema, TypeScript will show the correct types.

---

### Step 7: Regenerate OpenAPI Schema

**Action:** Export updated schema

```bash
php artisan scramble:export --path=api.json
```

**Why:** This updates the schema with the corrected Resource definitions.

**Verify:** Check that the schema now has the correct field names.

---

### Step 8: Regenerate Frontend Types

**Action:** Generate TypeScript types from updated schema

```bash
cd /Users/satriyo/dev/laravel-project/front-end-enter365
npm run types:generate
```

**What it does:** Runs `openapi-typescript` to generate `src/api/types.ts` from `api.json`

**Verify:** Check `src/api/types.ts` has correct field names

---

### Step 9: Run PHPStan Type Check

**Action:** Verify type safety of backend changes

```bash
# Check specific files
vendor/bin/phpstan analyse app/Http/Resources/Api/V1/{Resource}Resource.php --level=5

# Or use helper script
./scripts/phpstan-check.sh app/Http/Resources/Api/V1/{Resource}Resource.php
```

**What to check:**
- PHPDoc `@return array{...}` matches implementation
- No type mismatches
- Property access is type-safe

**Expected:** `[OK] No errors`

---

### Step 10: Verify Mismatch Detection Again

**Action:** Re-run mismatch checker to confirm fixes

```bash
php check-api-mismatches.php | grep -E "(QuotationResource|PurchaseOrderResource)"
```

**Expected:** No mismatches for the fixed resources

---

### Step 11: Run Contract Tests

**Action:** Verify API responses match schema

```bash
php artisan test --filter=ApiContractTest
```

**What it does:**
- Tests actual API responses
- Validates response structure
- Checks field types
- Ensures required fields are present

**Focus on:**
- Endpoints you modified
- Response structure validation
- Type checking

---

### Step 12: Run Full Test Suite

**Action:** Ensure all tests pass

```bash
php artisan test
```

**Focus on:**
- API tests for the changed resources
- Contract tests
- Integration tests
- Frontend API integration (if applicable)

---

### Step 13: Enable Runtime Validation (Optional)

**Action:** Enable response validation middleware for development

```env
# .env
API_RESPONSE_VALIDATION_ENABLED=true
```

**What it does:**
- Validates responses at runtime
- Logs mismatches immediately
- Great for debugging

**Note:** Disabled by default. Enable in development to catch issues early.

---

### Step 14: Manual Verification

**Action:** Spot-check the integration

**Backend:**
- Check API response in browser/Postman
- Verify field names match schema
- Check logs for validation warnings (if enabled)

**Frontend:**
- Check TypeScript types are correct
- Verify no type errors in IDE
- Test API calls work correctly

---

## Quick Reference Checklist

Use this checklist for each integration check:

- [ ] OpenAPI schema is up-to-date (`api.json`)
- [ ] Run mismatch detection script
- [ ] Identify critical mismatches
- [ ] Fix backend Resources (field names, types)
- [ ] Update PHPDoc to match implementation
- [ ] Update tests to match new field names
- [ ] Run API tests - all passing
- [ ] Run contract tests - all passing
- [ ] Update frontend (if field names changed)
- [ ] Regenerate OpenAPI schema
- [ ] Regenerate frontend types
- [ ] Run PHPStan - no errors
- [ ] Re-run mismatch checker - no mismatches
- [ ] Run full test suite
- [ ] Enable runtime validation (optional, development)
- [ ] Manual verification

---

## Common Patterns & Decisions

### Field Naming Consistency

**Standard:** Use `_amount` suffix for monetary values
- ✅ `total_amount`, `discount_amount`, `tax_amount`
- ❌ `total`, `discount`, `tax` (ambiguous)

**Exception:** `subtotal` (no suffix, but clear from context)

### Type Consistency

**Standard:** Match database column types
- `int` for amounts (stored as cents/smallest unit)
- `float` for rates/percentages
- `string` for dates (ISO8601 format)

### Date Formats

**Standard:** ISO8601 strings
- `created_at: string` (e.g., "2024-01-25T10:30:00+00:00")
- Use `->toIso8601String()` in Resources

---

## Tools & Scripts

### Mismatch Detection Script
**File:** `check-api-mismatches.php`
**Usage:** `php check-api-mismatches.php`
**Output:** Lists all resources with field mismatches

### PHPStan Check Script
**File:** `scripts/phpstan-check.sh`
**Usage:** `./scripts/phpstan-check.sh [path]`
**Output:** Type analysis results

### OpenAPI Export
**Command:** `php artisan scramble:export --path=api.json`
**Purpose:** Generate/update OpenAPI schema

### Frontend Type Generation
**Command:** `npm run types:generate` (in frontend directory)
**Purpose:** Generate TypeScript types from `api.json`

---

## Example: Our `total_amount` Fix

### Problem Identified
- QuotationResource returned `total_amount`
- OpenAPI schema had `total`
- Frontend used `total_amount` (worked at runtime, wrong types)

### Decision
- Standardize on `total_amount` (consistent with InvoiceResource, BillResource)

### Changes Made
1. ✅ Fixed PurchaseOrderResource: `total` → `total_amount`
2. ✅ Updated QuotationApiTest: `data.total` → `data.total_amount`
3. ✅ Updated PurchaseOrderApiTest: `data.total` → `data.total_amount`
4. ✅ Regenerated OpenAPI schema
5. ✅ Verified with PHPStan: No errors
6. ✅ All tests passing

### Result
- ✅ Consistent field naming across all resources
- ✅ Type-safe (PHPStan verified)
- ✅ Tests passing
- ✅ Frontend types correct

---

## When to Run This Flow

**Run this check when:**
- Adding new API endpoints
- Modifying existing API Resources
- Changing field names or types
- Before major releases
- When frontend reports type mismatches
- After refactoring API structure

**Frequency:** 
- Before each PR that touches API Resources
- Weekly/monthly full codebase check
- After major API changes

---

## Troubleshooting

### Mismatch Script Shows False Positives
- Check for conditional fields (`whenLoaded`, `when`)
- Verify nested resources are properly defined in schema

### PHPStan Fails with TCP Server Error
- Use `maximumNumberOfProcesses: 0` in `phpstan.neon`
- Or use `--debug` flag as workaround

### Frontend Types Don't Update
- Regenerate: `npm run types:generate`
- Check `api.json` was updated
- Verify `openapi-typescript` is working

### Tests Fail After Changes
- Update test assertions to match new field names
- Check for hardcoded field names in test data
- Verify factory methods match new structure

---

## Best Practices

1. **Always regenerate schema after Resource changes**
2. **Keep PHPDoc in sync with implementation**
3. **Use consistent naming across all resources**
4. **Run PHPStan before committing**
5. **Update tests immediately when changing fields**
6. **Document breaking changes in API versioning**

---

## Related Files

- `check-api-mismatches.php` - Mismatch detection script
- `api.json` - OpenAPI schema (source of truth)
- `phpstan.neon` - PHPStan configuration
- `scripts/phpstan-check.sh` - PHPStan helper script
- `API_MISMATCHES_REPORT.md` - Detailed mismatch report
- `README_PHPSTAN.md` - PHPStan usage guide
