# Backend-Frontend Integration Check - Quick Reference

## 🚀 Quick Flow (5 Minutes)

```bash
# 1. Update OpenAPI schema
php artisan scramble:export --path=api.json

# 2. Check for mismatches
php check-api-mismatches.php

# 3. Fix issues in Resources
# Edit: app/Http/Resources/Api/V1/{Resource}Resource.php

# 4. Update tests
# Edit: tests/Feature/Api/V1/{Resource}ApiTest.php

# 5. Run tests
php artisan test --filter={Resource}ApiTest

# 6. Regenerate schema
php artisan scramble:export --path=api.json

# 7. Regenerate frontend types (in frontend directory)
cd ../front-end-enter365 && npm run types:generate

# 8. Verify with PHPStan
cd ../enter365 && ./scripts/phpstan-check.sh app/Http/Resources/Api/V1/{Resource}Resource.php

# 9. Verify mismatches fixed
php check-api-mismatches.php | grep {Resource}
```

---

## 📋 Detailed 12-Step Flow

### 1. Generate OpenAPI Schema
```bash
php artisan scramble:export --path=api.json
```

### 2. Run Mismatch Detection
```bash
php check-api-mismatches.php
```
**Output:** Lists all resources with field mismatches

### 3. Identify Critical Issues
- Field name mismatches (prioritize)
- Missing fields in schema
- Type inconsistencies
- Resources used by frontend

### 4. Fix Backend Resources
- Update `toArray()` method
- Update PHPDoc `@return array{...}`
- Ensure consistency with other resources

### 5. Update Tests
- Change `assertJsonPath('data.field', ...)` assertions
- Update test data if needed

### 6. Run Tests
```bash
php artisan test --filter={Resource}ApiTest
```

### 7. Update Frontend (if needed)
- Update Vue components using the field
- Update API hooks if needed

### 8. Regenerate OpenAPI Schema
```bash
php artisan scramble:export --path=api.json
```

### 9. Regenerate Frontend Types
```bash
cd ../front-end-enter365
npm run types:generate
```

### 10. Run PHPStan
```bash
cd ../enter365
./scripts/phpstan-check.sh app/Http/Resources/Api/V1/{Resource}Resource.php
```

### 11. Verify Mismatches Fixed
```bash
php check-api-mismatches.php | grep {Resource}
```
Should show: No mismatches

### 12. Run Full Test Suite
```bash
php artisan test
```

---

## 🎯 What We Did (Example: total_amount Fix)

1. ✅ Ran `check-api-mismatches.php` → Found `total` vs `total_amount` mismatch
2. ✅ Decided: Use `total_amount` (consistent with InvoiceResource, BillResource)
3. ✅ Fixed QuotationResource: Already had `total_amount` ✓
4. ✅ Fixed PurchaseOrderResource: Changed `total` → `total_amount`
5. ✅ Updated QuotationApiTest: `data.total` → `data.total_amount`
6. ✅ Updated PurchaseOrderApiTest: `data.total` → `data.total_amount`
7. ✅ Ran tests: All passing ✓
8. ✅ Regenerated OpenAPI schema
9. ✅ Verified with PHPStan: No errors ✓
10. ✅ Re-ran mismatch checker: No mismatches ✓

---

## 🔧 Tools & Commands

| Tool | Command | Purpose |
|------|---------|---------|
| **Mismatch Detector** | `php check-api-mismatches.php` | Find field mismatches |
| **OpenAPI Export** | `php artisan scramble:export --path=api.json` | Generate schema |
| **Type Check** | `./scripts/phpstan-check.sh [path]` | Verify type safety |
| **Frontend Types** | `npm run types:generate` | Generate TS types |
| **Tests** | `php artisan test --filter={Resource}` | Run specific tests |

---

## 📝 Decision Framework

When fixing mismatches, consider:

1. **Consistency** - What do other similar resources use?
2. **Business Intent** - What makes semantic sense?
3. **Database** - What's the actual column name?
4. **Frontend Usage** - What does frontend expect?
5. **Type Safety** - What provides better type checking?

**Example:** We chose `total_amount` because:
- ✅ Consistent with InvoiceResource, BillResource
- ✅ Explicit (clearly a monetary amount)
- ✅ Matches database column name
- ✅ Better type safety

---

## ⚠️ Common Pitfalls

1. **Forgetting to regenerate schema** after Resource changes
2. **Not updating tests** when changing field names
3. **PHPDoc out of sync** with implementation
4. **Frontend types not regenerated** after schema update
5. **Conditional fields** not in schema (use `whenLoaded`, `when`)

---

## ✅ Success Criteria

- [ ] Mismatch checker shows no errors for changed resources
- [ ] PHPStan reports no type errors
- [ ] All tests passing
- [ ] Frontend types match backend schema
- [ ] OpenAPI schema is up-to-date
- [ ] Field naming is consistent across resources

---

## 📚 Related Documentation

- `INTEGRATION_CHECK_FLOW.md` - Detailed step-by-step guide
- `API_MISMATCHES_REPORT.md` - Full mismatch analysis
- `check-api-mismatches.php` - Mismatch detection script
- `README_PHPSTAN.md` - PHPStan usage guide
