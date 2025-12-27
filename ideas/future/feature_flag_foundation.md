# The Key: Abstraction Layer

Instead of calling config() directly everywhere, we funnel all access through the Features class. This becomes our single point of change when we need runtime/multi-tenant support.

  ┌─────────────────────────────────────────────────────────────────────┐
  │                      EVOLUTION PATH                                 │
  ├─────────────────────────────────────────────────────────────────────┤
  │                                                                     │
  │   NOW                    LATER                   FUTURE             │
  │   ────                   ─────                   ──────             │
  │                                                                     │
  │   Config-based    →    Database-based     →    Multi-tenant         │
  │   (env file)           (single tenant)         (per-tenant)         │
  │                                                                     │
  │   Features::enabled()  remains the same interface everywhere        │
  │   Only the internal implementation changes                          │
  │                                                                     │
  └─────────────────────────────────────────────────────────────────────┘
---

## Design: Contract-Based Architecture

### Phase 1 (Now): Simple config-based implementation
```
  // app/Contracts/FeatureManager.php

  <?php

  namespace App\Contracts;

  interface FeatureManager
  {
      public function enabled(string $module, ?int $tenantId = null): bool;

      public function disabled(string $module, ?int $tenantId = null): bool;

      public function enabledModules(?int $tenantId = null): array;

      public function disabledModules(?int $tenantId = null): array;

      public function all(?int $tenantId = null): array;
  }

  // app/Support/ConfigFeatureManager.php (Current Implementation)
  <?php

  namespace App\Support;

  use App\Contracts\FeatureManager;

  class ConfigFeatureManager implements FeatureManager
  {
      public function enabled(string $module, ?int $tenantId = null): bool
      {
          // tenantId ignored for now - single tenant
          return config("features.modules.{$module}", true);
      }

      public function disabled(string $module, ?int $tenantId = null): bool
      {
          return !$this->enabled($module, $tenantId);
      }

      public function enabledModules(?int $tenantId = null): array
      {
          return collect(config('features.modules', []))
              ->filter(fn ($enabled) => $enabled === true)
              ->keys()
              ->toArray();
      }

      public function disabledModules(?int $tenantId = null): array
      {
          return collect(config('features.modules', []))
              ->filter(fn ($enabled) => $enabled === false)
              ->keys()
              ->toArray();
      }

      public function all(?int $tenantId = null): array
      {
          return config('features.modules', []);
      }
  }

  // app/Support/Features.php (Facade-like static accessor)
  <?php

  namespace App\Support;

  use App\Contracts\FeatureManager;

  class Features
  {
      public static function enabled(string $module): bool
      {
          return app(FeatureManager::class)->enabled($module);
      }

      public static function disabled(string $module): bool
      {
          return app(FeatureManager::class)->disabled($module);
      }

      public static function enabledModules(): array
      {
          return app(FeatureManager::class)->enabledModules();
      }

      public static function disabledModules(): array
      {
          return app(FeatureManager::class)->disabledModules();
      }

      public static function all(): array
      {
          return app(FeatureManager::class)->all();
      }
  }

  // app/Providers/AppServiceProvider.php
  public function register(): void
  {
      $this->app->singleton(
          \App\Contracts\FeatureManager::class,
          \App\Support\ConfigFeatureManager::class
      );
  }

  ---
```
## Future Evolution: Database-Based (Runtime Configurable)

  When you need runtime configuration:
```
  // database/migrations/xxxx_create_feature_flags_table.php
  Schema::create('feature_flags', function (Blueprint $table) {
      $table->id();
      $table->string('module')->index();
      $table->boolean('enabled')->default(true);
      $table->text('description')->nullable();
      $table->timestamps();

      $table->unique('module');
  });

  // app/Support/DatabaseFeatureManager.php
  <?php

  namespace App\Support;

  use App\Contracts\FeatureManager;
  use App\Models\FeatureFlag;
  use Illuminate\Support\Facades\Cache;

  class DatabaseFeatureManager implements FeatureManager
  {
      public function enabled(string $module, ?int $tenantId = null): bool
      {
          return Cache::remember("features.{$module}", 3600, function () use ($module) {
              $flag = FeatureFlag::where('module', $module)->first();

              // Default to config if not in database
              return $flag ? $flag->enabled : config("features.modules.{$module}", true);
          });
      }

      // ... other methods with caching
  }

  To switch: Just change the binding in AppServiceProvider:

  $this->app->singleton(
      \App\Contracts\FeatureManager::class,
      \App\Support\DatabaseFeatureManager::class  // Changed!
  );
```
  All existing code continues to work - no changes needed elsewhere.

  ---
## Future Evolution: Multi-Tenant

  When you add multi-tenancy:
```
  // database/migrations/xxxx_create_tenant_features_table.php
  Schema::create('tenant_features', function (Blueprint $table) {
      $table->id();
      $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
      $table->string('module')->index();
      $table->boolean('enabled')->default(true);
      $table->timestamps();

      $table->unique(['tenant_id', 'module']);
  });

  // app/Support/TenantFeatureManager.php
  <?php

  namespace App\Support;

  use App\Contracts\FeatureManager;
  use App\Models\TenantFeature;
  use Illuminate\Support\Facades\Cache;

  class TenantFeatureManager implements FeatureManager
  {
      public function enabled(string $module, ?int $tenantId = null): bool
      {
          $tenantId = $tenantId ?? tenant()?->id;

          if (!$tenantId) {
              // Fallback to config for non-tenant context
              return config("features.modules.{$module}", true);
          }

          return Cache::remember(
              "tenant.{$tenantId}.features.{$module}",
              3600,
              function () use ($module, $tenantId) {
                  $flag = TenantFeature::where('tenant_id', $tenantId)
                      ->where('module', $module)
                      ->first();

                  // Default to global config if tenant hasn't customized
                  return $flag ? $flag->enabled : config("features.modules.{$module}", true);
              }
          );
      }

      // ... other methods with tenant awareness
  }

  // app/Http/Middleware/FeatureEnabled.php (Updated for tenant)
  public function handle(Request $request, Closure $next, string $feature): Response
  {
      $tenantId = tenant()?->id;  // However your tenancy package resolves this

      if (Features::disabled($feature, $tenantId)) {
          abort(404, "Feature not available");
      }

      return $next($request);
  }
```
  ---
  Comparison: Implementation Phases

  | Phase        | Storage       | Configurable By    | Cache               | Migration Effort          |
  |--------------|---------------|--------------------|---------------------|---------------------------|
  | Now          | .env / config | Developer (deploy) | Config cache        | N/A                       |
  | Runtime      | Database      | Admin UI           | Redis/DB cache      | Add 1 table, swap binding |
  | Multi-tenant | Database      | Per-tenant admin   | Tenant-scoped cache | Add 1 table, swap binding |

  ---
  ## Why This Works

  ┌─────────────────────────────────────────────────────────────────────┐
  │                    DEPENDENCY INVERSION                              │
  ├─────────────────────────────────────────────────────────────────────┤
  │                                                                     │
  │   Your Code (Middleware, Resources, Requests)                       │
  │                    │                                                │
  │                    ▼                                                │
  │   Features::enabled('inventory')  ← Static facade                   │
  │                    │                                                │
  │                    ▼                                                │
  │   FeatureManager Interface  ← Contract                              │
  │                    │                                                │
  │         ┌─────────┼─────────┐                                       │
  │         ▼         ▼         ▼                                       │
  │   ConfigManager  DBManager  TenantManager  ← Implementations        │
  │   (now)          (later)    (future)                                │
  │                                                                     │
  │   Swap implementation = change 1 line in ServiceProvider            │
  │   All other code remains unchanged                                  │
  │                                                                     │
  └─────────────────────────────────────────────────────────────────────┘

  ---
  ## Summary

  | Question                        | Answer                                   |
  |---------------------------------|------------------------------------------|
  | Easy to extend to runtime?      | ✅ Yes, swap implementation binding      |
  | Easy to extend to multi-tenant? | ✅ Yes, same pattern with tenant context |
  | Extra complexity now?           | ❌ No, just one interface + one class    |
  | Breaking changes later?         | ❌ No, interface stays the same          |
  | External packages needed?       | ❌ None                                  |

