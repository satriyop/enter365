---
adr: "0044"
title: "Multi-Tenant Readiness"
status: proposed
date: 2024-11-15
deciders: [Product Team]
tags: [architecture, scaling]
related_adrs: [0002]
related_modules: [core]
impact: high
---

# ADR-0044: Multi-Tenant Readiness

## AI Agent Quick Reference

**Use this ADR when:**
- Planning for multi-company support
- Understanding tenant isolation
- Implementing data separation
- Scaling architecture

**Key takeaway:** Currently single-tenant; designed for future multi-tenant with tenant_id columns.

---

## Decision

Design with multi-tenant awareness using tenant_id columns, but deploy as single-tenant initially.

---

## Context

Future needs:
1. Multiple companies in one instance
2. Data isolation between tenants
3. Shared resources (users, products)
4. SaaS deployment option

---

## Implementation

### Tenant Model (Future)

```php
// tenants table
$table->string('name');                  // Company name
$table->string('slug');                  // subdomain/identifier
$table->string('database')->nullable();  // For separate DB strategy
$table->json('settings');
$table->boolean('is_active')->default(true);
```

### Current Single-Tenant

```php
// No tenant_id columns currently
// All data belongs to one company
// User belongs to company via settings
```

### Future Migration Path

```php
// When adding multi-tenancy:
// 1. Add tenant_id to all relevant tables
$table->foreignId('tenant_id')->after('id');

// 2. Add global scope
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('tenant_id', tenant()->id);
    }
}

// 3. Use trait on models
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $model->tenant_id = tenant()->id;
        });
    }
}
```

### Tenant Resolution (Future)

```php
// By subdomain
// company1.enter365.com → tenant 1
// company2.enter365.com → tenant 2

// Middleware
class IdentifyTenant
{
    public function handle($request, $next)
    {
        $tenant = Tenant::where('slug', $request->getHost())
            ->firstOrFail();

        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
```

### Database Strategies

| Strategy | Isolation | Complexity | Use Case |
|----------|-----------|------------|----------|
| Shared DB + Column | Low | Low | SMEs, low data |
| Separate Schema | Medium | Medium | Mid-size |
| Separate DB | High | High | Enterprise |

### Current Design Considerations

```php
// Tables ready for tenant_id:
// - All transaction tables (invoices, bills, payments)
// - All master data (products, contacts, warehouses)
// - All configuration (payment_terms, tax_rates)

// Shared resources (no tenant_id):
// - users (can belong to multiple tenants)
// - system settings
// - audit logs (include tenant context)
```

### Migration Checklist (Future)

1. Add tenant_id to 60+ tables
2. Add foreign key constraints
3. Migrate existing data (set tenant_id = 1)
4. Implement global scopes
5. Update all queries
6. Add tenant middleware
7. Create tenant admin UI

### Current Recommendation

```
For now: Single-tenant deployment
Future: Add multi-tenancy when needed

Keep code patterns that support future multi-tenancy:
- Avoid raw SQL without tenant context
- Use Eloquent models (can add scopes later)
- Don't hardcode single-company assumptions
```

---

## References

- [ADR-0002: PostgreSQL Database](./0002-postgresql-database.md)
- [System Overview](../01-architecture/system-overview.md)

