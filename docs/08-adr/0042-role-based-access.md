---
adr: "0042"
title: "Role-Based Access Control"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [security, authorization]
related_adrs: [0004]
related_modules: [core]
impact: high
---

# ADR-0042: Role-Based Access Control

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing authorization
- Creating new permissions
- Working with user roles
- Building access control

**Key takeaway:** RBAC with roles containing multiple permissions, using Laravel Policies for model authorization.

---

## Decision

Implement role-based access control with permissions using Laravel Gates and Policies.

---

## Context

Authorization needs:
1. User role management
2. Permission granularity
3. Module-level access
4. Resource-level permissions

---

## Implementation

### Database Schema

```php
// roles table
$table->string('name');                  // Admin, Accountant, Sales
$table->string('slug');                  // admin, accountant, sales
$table->text('description')->nullable();

// permissions table
$table->string('name');                  // View Invoices
$table->string('slug');                  // invoices.view
$table->string('module');                // invoices
$table->string('action');                // view, create, update, delete, approve

// role_permission pivot
$table->foreignId('role_id');
$table->foreignId('permission_id');

// user has role
$table->foreignId('role_id')->on('users');
```

### Standard Roles

| Role | Description | Typical Permissions |
|------|-------------|---------------------|
| admin | Full access | All permissions |
| manager | Approvals + reports | View all, approve, reports |
| accountant | Accounting functions | Journals, invoices, payments |
| sales | Sales functions | Quotations, invoices (no approve) |
| warehouse | Inventory functions | Stock, GRN, transfers |
| viewer | Read only | View permissions only |

### Permission Naming

```
{module}.{action}

Examples:
invoices.view
invoices.create
invoices.update
invoices.delete
invoices.approve
invoices.send
```

### Role Model

```php
class Role extends Model
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('slug', $permission);
    }
}
```

### User Authorization

```php
class User extends Authenticatable
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions
        if ($this->role->slug === 'admin') {
            return true;
        }

        return $this->role->hasPermission($permission);
    }

    public function hasRole(string $role): bool
    {
        return $this->role->slug === $role;
    }
}
```

### Gate Registration

```php
// AppServiceProvider
public function boot(): void
{
    Gate::before(function (User $user, string $ability) {
        if ($user->hasRole('admin')) {
            return true;  // Admin bypasses all checks
        }
    });

    Permission::all()->each(function ($permission) {
        Gate::define($permission->slug, function (User $user) use ($permission) {
            return $user->hasPermission($permission->slug);
        });
    });
}
```

### Policy Usage

```php
// app/Policies/InvoicePolicy.php
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.update')
            && $invoice->canEdit();
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.approve')
            && $invoice->status === 'draft';
    }
}
```

### Controller Authorization

```php
public function store(StoreInvoiceRequest $request)
{
    $this->authorize('create', Invoice::class);
    // ...
}

public function approve(Invoice $invoice)
{
    $this->authorize('approve', $invoice);
    // ...
}
```

### Blade Directives

```blade
@can('invoices.create')
    <button>Create Invoice</button>
@endcan

@role('admin')
    <a href="/admin">Admin Panel</a>
@endrole
```

---

## References

- [ADR-0004: Sanctum Authentication](./0004-sanctum-authentication.md)
- [Service Layer](../01-architecture/service-layer.md)

