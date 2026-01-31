---
section: development
title: "Development Workflow"
order: 1
updated: 2026-01-25
---

# Development Workflow

> **Complete guide to the development process, code quality checks, and automated validation**

---

## AI Agent Quick Reference

**Use this document when:**
- Understanding the development workflow
- Setting up automated checks
- Running code quality tools
- Validating API contracts

**Key takeaway:** The project uses automated validation for API contracts, type checking, and code formatting.

---

## Development Flow Overview

```
1. Write/Modify Code
   ↓
2. Run Code Formatter (Pint)
   ↓
3. Run Type Checker (PHPStan)
   ↓
4. Run API Contract Validation
   ↓
5. Run Tests
   ↓
6. Commit Changes
```

---

## Code Quality Checks

### 1. Code Formatting (Laravel Pint)

**Always run before committing:**

```bash
# Format only changed files
vendor/bin/pint --dirty

# Format specific file
vendor/bin/pint app/Services/YourService.php

# Format entire directory
vendor/bin/pint app/Services/
```

**Configuration:** Uses project defaults, no config file needed.

---

### 2. Type Checking (PHPStan/Larastan)

**Run after modifying PHP code:**

```bash
# Quick check (recommended)
./scripts/phpstan-check.sh app/Services/YourService.php

# Full analysis
vendor/bin/phpstan analyse

# Analyze specific directory
vendor/bin/phpstan analyse app/Services/Sales/

# With memory limit for large analysis
vendor/bin/phpstan analyse --memory-limit=512M
```

**Configuration:**
- **Config file:** `phpstan.neon`
- **Baseline:** `phpstan-baseline.neon` (~1844 existing errors)
- **Level:** 5 (good balance of strictness vs noise)

**What PHPStan Catches:**
- Type mismatches
- Missing methods
- Null safety issues
- Return type violations
- Argument count errors

**macOS Note:** If PHPStan TCP server fails, it's configured to run in single-process mode automatically.

---

### 3. API Contract Validation

**Run after modifying API Resources or endpoints:**

```bash
# Full integration check (recommended)
./scripts/check-api-integration.sh

# Skip tests (faster)
./scripts/check-api-integration.sh --no-tests

# Skip PHPStan (faster)
./scripts/check-api-integration.sh --no-phpstan

# Just check mismatches
php check-api-mismatches.php
```

**What It Checks:**
1. Generates OpenAPI schema (`api.json`)
2. Detects field name mismatches
3. Validates `api.json` structure
4. Runs PHPStan on API Resources
5. Runs API tests
6. Runs contract tests
7. Checks frontend integration

**Pre-commit Hook:**
- Automatically runs on commits that modify API files
- Blocks commit if validation fails
- Ensures `api.json` is staged

**CI/CD:**
- Runs on pull requests
- Validates API contract changes
- Blocks merge if issues found

---

## Automated Validation

### Pre-commit Hook

**Installation:**
```bash
./scripts/install-pre-commit-hook.sh
```

**What It Does:**
- Runs on every commit
- Generates OpenAPI schema
- Checks for API contract mismatches
- Validates `api.json` structure
- Blocks commit if `api.json` not staged

**Files Monitored:**
- `app/Http/Resources/Api/**`
- `app/Http/Controllers/Api/**`
- `routes/api.php`
- `api.json`

---

### CI/CD Integration

**GitHub Actions Workflow:** `.github/workflows/api-contract-check.yml`

**Triggers:**
- Pull requests modifying API files
- Pushes to `main`/`develop` branches

**Steps:**
1. Generate OpenAPI schema
2. Check for contract mismatches
3. Validate `api.json`
4. Run PHPStan on API Resources
5. Check for `api.json` changes

**Status:** Automatically runs on relevant PRs.

---

## API Contract Validation Details

### Field Naming Standards

**Monetary Fields:**
- Use `total_amount` (not `total`)
- Use `discount_amount` (not `discount`)
- Use `tax_amount` (not `tax`)
- All amounts are integers (stored in smallest currency unit)

**Consistency:**
- Database columns match Resource field names
- OpenAPI schema matches Resource output
- Frontend types match API responses

---

### Response Validation Middleware

**Location:** `app/Http/Middleware/ValidateApiResponse.php`

**Purpose:** Validates API responses against OpenAPI schema at runtime.

**Configuration:**
```env
# Enable in development
API_RESPONSE_VALIDATION_ENABLED=true

# Strict mode (throws exceptions)
API_RESPONSE_VALIDATION_STRICT=false
```

**Features:**
- Validates response structure
- Checks field types
- Logs validation failures
- Non-blocking by default

---

### Contract Tests

**Location:** `tests/Contract/`

**Files:**
- `ApiContractTest.php` - Basic contract validation
- `ApiContractEdgeCasesTest.php` - Edge cases and boundary conditions

**Coverage:**
- Quotations, Invoices, Products, Contacts
- Pagination, Error responses
- Empty collections, Type consistency
- 32 edge case tests

**Run:**
```bash
php artisan test --filter=ApiContractTest
php artisan test tests/Contract/
```

---

## Testing Workflow

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Api/V1/QuotationApiTest.php

# Run with filter
php artisan test --filter=QuotationService

# Run contract tests
php artisan test --filter=ApiContractTest
```

### Test Organization

- **Feature Tests:** `tests/Feature/` - Full HTTP/API tests
- **Unit Tests:** `tests/Unit/` - Isolated unit tests
- **Contract Tests:** `tests/Contract/` - API contract validation
- **Domain Tests:** `tests/Feature/Domain/` - Domain logic tests

---

## Helper Scripts

### Available Scripts

| Script | Purpose |
|--------|---------|
| `./scripts/check-api-integration.sh` | Full API contract validation |
| `./scripts/phpstan-check.sh` | PHPStan type checking |
| `./scripts/install-pre-commit-hook.sh` | Install pre-commit hook |
| `./scripts/check-pattern-compliance.sh` | Pattern compliance check |

---

## Development Best Practices

### 1. Before Committing

```bash
# 1. Format code
vendor/bin/pint --dirty

# 2. Type check (if modified PHP)
./scripts/phpstan-check.sh app/YourModifiedFile.php

# 3. API contract check (if modified API)
./scripts/check-api-integration.sh

# 4. Run tests
php artisan test --filter=YourTest

# 5. Commit
git add .
git commit -m "Your message"
```

### 2. When Modifying API Resources

1. Update Resource class
2. Run `./scripts/check-api-integration.sh`
3. Fix any mismatches
4. Update related tests
5. Regenerate frontend types (if needed)
6. Commit with `api.json` staged

### 3. When Creating New Services

1. Extend `BaseService`
2. Add traits as needed (`WithTransaction`, `WithEventDispatching`, etc.)
3. Implement interface
4. Write tests
5. Run PHPStan
6. Format code

---

## Common Workflows

### Adding New API Endpoint

1. Create Form Request (`php artisan make:request Api/V1/StoreXxxRequest`)
2. Create/Update Controller method
3. Create/Update API Resource
4. Add route to `routes/api.php`
5. Run `./scripts/check-api-integration.sh`
6. Write/update tests
7. Run tests
8. Commit with `api.json` staged

### Modifying Service

1. Update service class
2. Run PHPStan: `./scripts/phpstan-check.sh app/Services/YourService.php`
3. Update tests if needed
4. Run tests: `php artisan test --filter=YourService`
5. Format: `vendor/bin/pint app/Services/YourService.php`
6. Commit

### Fixing API Contract Mismatch

1. Run `php check-api-mismatches.php` to identify issues
2. Fix Resource field names (use `total_amount`, not `total`)
3. Update tests to match
4. Run `./scripts/check-api-integration.sh`
5. Verify all checks pass
6. Commit with `api.json` staged

---

## Troubleshooting

### PHPStan TCP Server Error (macOS)

**Issue:** `Failed to start TCP server`

**Solution:** PHPStan is configured to run in single-process mode automatically. If issues persist:

1. Check macOS firewall settings
2. Use `./scripts/phpstan-check.sh` (handles this automatically)
3. Or set `maximumNumberOfProcesses: 0` in `phpstan.neon`

### API Contract Check Fails

**Check:**
1. Is `api.json` up to date? Run `php artisan scramble:export --path=api.json`
2. Are field names consistent? Use `total_amount`, not `total`
3. Are tests updated? Run `php artisan test --filter=ApiContractTest`

### Pre-commit Hook Not Running

**Check:**
1. Is hook installed? `ls -la .git/hooks/pre-commit`
2. Is it executable? `chmod +x .git/hooks/pre-commit`
3. Reinstall: `./scripts/install-pre-commit-hook.sh`

---

## Related Documentation

- [API Contract Validation Flow](../../INTEGRATION_CHECK_FLOW.md)
- [PHPStan Setup](../../README_PHPSTAN.md)
- [Service Pattern](../07-code-patterns/service-pattern.md)
- [Testing Pattern](../07-code-patterns/testing-pattern.md)

---

## Summary

**Key Tools:**
- ✅ Laravel Pint - Code formatting
- ✅ PHPStan - Type checking
- ✅ API Contract Validation - Schema consistency
- ✅ Pre-commit Hook - Automated checks
- ✅ CI/CD - Continuous validation

**Workflow:**
1. Write code
2. Format (Pint)
3. Type check (PHPStan)
4. Contract validate (if API)
5. Test
6. Commit

**Automation:**
- Pre-commit hook runs automatically
- CI/CD validates on PRs
- Helper scripts simplify common tasks
