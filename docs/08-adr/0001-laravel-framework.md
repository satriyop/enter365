---
adr: "0001"
title: "Choose Laravel 12 as Application Framework"
status: accepted
date: 2024-11-01
deciders: [Architecture Team]
tags: [framework, backend, technology]
related_adrs: [0002, 0003, 0004]
related_modules: [all]
impact: high
---

# ADR-0001: Choose Laravel 12 as Application Framework

## AI Agent Quick Reference

**Use this ADR when:**
- Understanding the technology foundation of Enter365
- Evaluating framework-specific patterns and conventions
- Considering framework alternatives or upgrades

**Key takeaway:** Laravel 12 was chosen for its rich ecosystem, Eloquent ORM, and strong Indonesian developer community support.

---

## Context

Enter365 required a robust PHP framework for building a RESTful accounting API targeting Indonesian SMEs in the electrical panel manufacturing and solar EPC industries.

### Forces

- **Business Requirements:**
  - SAK EMKM (Indonesian SME accounting standard) compliance
  - Indonesian localization (dates, currency, tax calculations)
  - Multi-module system (sales, purchasing, manufacturing, MRP, solar)
  - Rapid development for competitive market entry

- **Technical Constraints:**
  - PHP expertise available in team
  - Need for robust ORM for complex accounting relationships
  - RESTful API with OpenAPI documentation
  - Token-based authentication for mobile/web clients

- **Team Context:**
  - Strong Laravel ecosystem familiarity
  - Preference for convention-over-configuration
  - Need for extensive testing support

---

## Decision Drivers

1. **ORM Capabilities** - Complex accounting relationships (70+ models)
2. **API Development** - Built-in tools for RESTful APIs
3. **Authentication** - Sanctum for token-based auth
4. **Testing** - First-class testing support
5. **Ecosystem** - Rich package ecosystem
6. **Community** - Strong Indonesian Laravel community

---

## Considered Options

### Option 1: Laravel 12 (Chosen)

**Description:** Latest stable Laravel with streamlined structure

**Pros:**
- Eloquent ORM handles complex relationships naturally
- Built-in Sanctum for API authentication
- Service container for dependency injection
- Rich ecosystem (Scramble, Pest, Pint)
- Strong Indonesian community support
- Convention over configuration

**Cons:**
- Framework lock-in
- Upgrade burden with major versions
- Opinionated structure

### Option 2: Symfony + API Platform

**Description:** Symfony framework with API Platform bundle

**Pros:**
- More flexible architecture
- Strong enterprise support
- Better for microservices

**Cons:**
- Steeper learning curve
- More boilerplate code
- Smaller Indonesian community

### Option 3: Custom PHP + Libraries

**Description:** Custom framework using individual libraries

**Pros:**
- Full control over architecture
- No framework lock-in

**Cons:**
- Slower development
- Maintenance burden
- Reinventing solved problems

---

## Decision

**Chosen option:** "Laravel 12"

Laravel 12 provides the best balance of rapid development, robust ORM, and ecosystem support for building Enter365's complex accounting system.

---

## Rationale

### Why Laravel 12:

1. **Eloquent ORM** - Simplifies complex accounting relationships
   - 70+ models with belongs-to, has-many, polymorphic relationships
   - Eager loading for N+1 prevention
   - Built-in soft deletes for audit compliance

2. **Service Container** - Enables clean service layer architecture
   - 39 services with dependency injection
   - Easy testing with mocks
   - Clear separation of concerns

3. **Authentication (Sanctum)** - Perfect fit for API tokens
   - Stateless token authentication
   - Multiple tokens per user (device support)
   - Simple implementation

4. **Migration System** - Database versioning (101 migrations)
   - Rollback support
   - Team collaboration
   - Production deployment safety

5. **Ecosystem** - Complete toolkit available
   - Pest v4 for testing (950+ tests)
   - Pint for code formatting
   - Scramble for OpenAPI docs

6. **Laravel 12 Specifics:**
   - Streamlined structure (no Kernel.php)
   - `bootstrap/app.php` for middleware
   - PHP 8.4 support with modern syntax

### Why not Symfony:

- More boilerplate for same functionality
- Smaller Indonesian developer pool
- Less integrated ecosystem

---

## Consequences

### Positive

- Rapid API development (418 routes implemented)
- Rich ecosystem integration (Sanctum, Scramble, Pest)
- Clean architecture enforcement via conventions
- Easy testing with factories and mocks
- Strong community support for issues

### Negative

- Framework lock-in (migration to other frameworks costly)
- Monolithic tendency (requires discipline for modularity)
- Version upgrade burden (must track Laravel releases)
- Memory overhead for large data operations

### Neutral

- Requires Laravel expertise for maintenance
- Database migrations are Laravel-specific format
- Some patterns differ from "pure" DDD approaches

---

## Implementation Notes

**File Locations:**

| Purpose | Path |
|---------|------|
| Bootstrap | `/bootstrap/app.php` |
| Configuration | `/config/` |
| API Routes | `/routes/api.php` |
| Controllers | `/app/Http/Controllers/Api/V1/` (53 files) |
| Models | `/app/Models/Accounting/` (70 files) |
| Services | `/app/Services/Accounting/` (39 files) |

**Laravel 12 Structure (Streamlined):**

```php
// File: /bootstrap/app.php

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
```

**Key Patterns:**

```php
// Service Layer Pattern (ADR-0003)
// File: /app/Services/Accounting/QuotationService.php

class QuotationService
{
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $quotation = Quotation::create($data);
            $this->syncItems($quotation, $data['items'] ?? []);
            $quotation->calculateTotals();
            return $quotation->fresh(['items', 'contact']);
        });
    }
}
```

---

## Validation

**Tests:** All tests in `/tests/` use Laravel testing utilities

**Verification:**
- Run `php artisan about` to confirm Laravel version
- Run `php artisan test` to verify framework integration
- Check `/bootstrap/app.php` for Laravel 12 structure

---

## References

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Laravel 12 Release Notes](https://laravel.com/docs/12.x/releases)
- [Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- ADR-0002: PostgreSQL Database
- ADR-0003: Service Layer Pattern
- ADR-0004: Sanctum Authentication

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Backend Team
