---
adr: "0007"
title: "Feature Flag System for Module Control"
status: accepted
date: 2024-11-01
deciders: [Architecture Team, Product Team]
tags: [architecture, modularity, configuration]
related_adrs: [0005, 0010]
related_modules: [all]
impact: high
---

# ADR-0007: Feature Flag System for Module Control

## AI Agent Quick Reference

**Use this ADR when:**
- Adding new feature modules
- Understanding module enabling/disabling
- Working with conditional feature access
- Debugging "Feature tidak tersedia" errors

**Key takeaway:** Modules are toggled via environment-based feature flags using the `Features` facade and `feature:modulename` middleware.

---

## Context

Enter365 has multiple functional modules:
- Core Accounting (always on)
- Sales (quotations, invoices)
- Purchasing (PO, GRN, bills)
- Inventory (warehouses, stock)
- Manufacturing (BOM, work orders)
- MRP (material requirements planning)
- Projects (cost tracking)
- Solar EPC (proposals, calculations)

Not all customers need all modules. SMEs may want:
- Only Sales + Purchasing
- Only Manufacturing features
- Full ERP functionality

---

## Decision Drivers

1. **Customer Flexibility** - Different needs per customer
2. **Clean UI** - Hide unused features
3. **License Control** - Enable features per subscription
4. **Gradual Rollout** - Enable new features selectively
5. **Development** - Test modules in isolation

---

## Considered Options

### Option 1: Environment Feature Flags (Chosen)

**Description:** Feature flags defined in environment/config, checked via middleware

**Pros:**
- Simple implementation
- Environment-based configuration
- No database overhead
- Fast checks (config lookup)
- Easy to understand

**Cons:**
- Requires redeploy for changes
- No per-user features
- No gradual rollout percentage

### Option 2: Database Feature Flags

**Description:** Feature states stored in database

**Pros:**
- Runtime changes without redeploy
- Per-tenant features possible
- Admin UI for toggling

**Cons:**
- Database lookup overhead
- More complex implementation
- Migration needed for new features

### Option 3: Third-Party Feature Flag Service

**Description:** Use LaunchDarkly, Flagsmith, or similar

**Pros:**
- Advanced targeting
- A/B testing
- Analytics

**Cons:**
- External dependency
- Cost
- Overkill for module control

---

## Decision

**Chosen option:** "Environment Feature Flags"

Simple, config-based feature flags controlled via middleware. Features are enabled/disabled via environment variables and checked using the `Features` facade.

---

## Rationale

### Why Environment Flags:

1. **Simplicity**
   - Define in `.env`, done
   - No database queries
   - No external services

2. **Deployment-Based**
   - Different `.env` per customer/environment
   - Clear configuration per deployment
   - Version controllable

3. **Performance**
   - Config cached in production
   - Zero runtime overhead after boot
   - No database queries per request

4. **Sufficient for Use Case**
   - Module-level toggling
   - Not user-level feature rollout
   - Binary on/off sufficient

---

## Consequences

### Positive

- Zero performance overhead
- Simple to understand
- Easy to configure per environment
- No database dependencies
- Works with config caching

### Negative

- Requires redeploy to change
- No runtime admin UI
- No percentage rollout
- No per-user features

### Neutral

- All-or-nothing per deployment
- Different customers = different deployments

---

## Implementation Notes

**Feature Configuration:**

```php
// File: /config/features.php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Modules
    |--------------------------------------------------------------------------
    |
    | Enable/disable feature modules for this deployment.
    | Disabled modules return 404 for their routes.
    |
    */
    'modules' => [
        'sales' => env('FEATURE_SALES', true),
        'purchasing' => env('FEATURE_PURCHASING', true),
        'inventory' => env('FEATURE_INVENTORY', true),
        'manufacturing' => env('FEATURE_MANUFACTURING', true),
        'mrp' => env('FEATURE_MRP', true),
        'projects' => env('FEATURE_PROJECTS', true),
        'solar' => env('FEATURE_SOLAR', true),
        'bank_reconciliation' => env('FEATURE_BANK_RECONCILIATION', true),
        'recurring' => env('FEATURE_RECURRING', true),
        'multi_currency' => env('FEATURE_MULTI_CURRENCY', false),
    ],
];
```

**Environment Variables:**

```bash
# File: /.env

# Enable all features
FEATURE_SALES=true
FEATURE_PURCHASING=true
FEATURE_INVENTORY=true
FEATURE_MANUFACTURING=true
FEATURE_MRP=true
FEATURE_PROJECTS=true
FEATURE_SOLAR=true
FEATURE_BANK_RECONCILIATION=true
FEATURE_RECURRING=true
FEATURE_MULTI_CURRENCY=false
```

**Feature Manager Contract:**

```php
// File: /app/Contracts/FeatureManager.php

namespace App\Contracts;

interface FeatureManager
{
    public function enabled(string $module): bool;
    public function disabled(string $module): bool;
    public function all(): array;
    public function enabledModules(): array;
    public function disabledModules(): array;
}
```

**Feature Manager Implementation:**

```php
// File: /app/Support/ConfigFeatureManager.php

namespace App\Support;

use App\Contracts\FeatureManager;

class ConfigFeatureManager implements FeatureManager
{
    public function enabled(string $module): bool
    {
        return config("features.modules.{$module}", false);
    }

    public function disabled(string $module): bool
    {
        return !$this->enabled($module);
    }

    public function all(): array
    {
        return config('features.modules', []);
    }

    public function enabledModules(): array
    {
        return array_keys(array_filter($this->all()));
    }

    public function disabledModules(): array
    {
        return array_keys(array_filter($this->all(), fn ($v) => !$v));
    }
}
```

**Features Facade:**

```php
// File: /app/Support/Features.php

namespace App\Support;

use App\Contracts\FeatureManager;

class Features
{
    public static function enabled(string $module): bool
    {
        return static::manager()->enabled($module);
    }

    public static function disabled(string $module): bool
    {
        return static::manager()->disabled($module);
    }

    public static function all(): array
    {
        return static::manager()->all();
    }

    protected static function manager(): FeatureManager
    {
        return app(FeatureManager::class);
    }
}
```

**Middleware:**

```php
// File: /app/Http/Middleware/EnsureFeatureEnabled.php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (Features::disabled($feature)) {
            abort(404, 'Feature tidak tersedia.');
        }

        return $next($request);
    }
}
```

**Middleware Registration:**

```php
// File: /bootstrap/app.php

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);
    })
    ->create();
```

**Route Usage:**

```php
// File: /routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    // MRP routes - only if MRP feature enabled
    Route::middleware('feature:mrp')->prefix('mrp')->group(function () {
        Route::get('/', [MrpController::class, 'index']);
        Route::post('/run', [MrpController::class, 'run']);
    });

    // Manufacturing routes
    Route::middleware('feature:manufacturing')->group(function () {
        Route::apiResource('boms', BomController::class);
        Route::apiResource('work-orders', WorkOrderController::class);
    });

    // Solar routes
    Route::middleware('feature:solar')->prefix('solar')->group(function () {
        Route::apiResource('proposals', SolarProposalController::class);
    });

    // Always-enabled routes (no feature middleware)
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('journal-entries', JournalEntryController::class);
});
```

**Blade Usage (if needed):**

```blade
@if(Features::enabled('manufacturing'))
    <a href="/manufacturing">Manufacturing</a>
@endif
```

**Service Usage:**

```php
// Check before expensive operations
if (Features::enabled('mrp')) {
    $this->mrpService->calculateDemand();
}
```

**API Endpoint to List Features:**

```php
// File: /app/Http/Controllers/Api/V1/FeatureController.php

class FeatureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'features' => Features::all(),
            'enabled' => Features::enabledModules(),
            'disabled' => Features::disabledModules(),
        ]);
    }
}
```

---

## Validation

**Verification Steps:**

1. Check `config/features.php` exists
2. Verify middleware registered in `bootstrap/app.php`
3. Test disabled feature returns 404
4. Test enabled feature works normally

**Tests:**

```php
// File: /tests/Feature/FeatureFlags/FeatureFlagTest.php

it('returns 404 for disabled feature routes', function () {
    config(['features.modules.mrp' => false]);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/mrp')
        ->assertNotFound()
        ->assertJson(['message' => 'Feature tidak tersedia.']);
});

it('allows access to enabled feature routes', function () {
    config(['features.modules.mrp' => true]);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/mrp')
        ->assertOk();
});
```

---

## References

- ADR-0005: Single Accounting Namespace
- ADR-0010: Configuration-Driven Rules
- `/app/Support/Features.php`
- `/app/Http/Middleware/EnsureFeatureEnabled.php`
- `/config/features.php`

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Product Team, Backend Team
