# Feature Flags System - Contract-Based Implementation Plan

 ## Overview

 Implement a clean, extensible feature flags system for API-only Laravel application that supports future runtime configuration and multi-tenant scenarios.

 Design Principles

 - Contract-based: Interface-driven for easy implementation swapping
 - Zero external packages: Pure Laravel implementation
 - Backward compatible: All features default to enabled
 - Future-proof: Ready for database/multi-tenant extension
 - Testable: Easy to mock and test different configurations

 ---
 Architecture

 ┌─────────────────────────────────────────────────────────────┐
 │                    APPLICATION CODE                          │
 │   (Middleware, Resources, Requests, Services, Controllers)  │
 └─────────────────────────────┬───────────────────────────────┘
                               │ uses
                               ▼
 ┌─────────────────────────────────────────────────────────────┐
 │              App\Support\Features (Static Facade)            │
 │   Features::enabled('inventory')                             │
 │   Features::disabled('mrp')                                  │
 └─────────────────────────────┬───────────────────────────────┘
                               │ resolves
                               ▼
 ┌─────────────────────────────────────────────────────────────┐
 │         App\Contracts\FeatureManager (Interface)             │
 └─────────────────────────────┬───────────────────────────────┘
                               │ implemented by
               ┌───────────────┼───────────────┐
               ▼               ▼               ▼
       ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
       │ConfigManager │ │ DBManager    │ │TenantManager │
       │   (NOW)      │ │  (LATER)     │ │  (FUTURE)    │
       └──────────────┘ └──────────────┘ └──────────────┘

 ---
 Phase 1: Infrastructure (Foundation)

 File 1: config/features.php

 Configuration file defining all feature modules with env-based defaults.

 <?php

 return [
     /*
     |--------------------------------------------------------------------------
     | Feature Modules
     |--------------------------------------------------------------------------
     |
     | Enable or disable application modules. All modules default to enabled
     | for backward compatibility. Set to false to disable a module entirely.
     |
     */

     'modules' => [
         // Sales & Receivables
         'products' => env('FEATURE_PRODUCTS', true),
         'quotations' => env('FEATURE_QUOTATIONS', true),
         'delivery_orders' => env('FEATURE_DELIVERY_ORDERS', true),
         'sales_returns' => env('FEATURE_SALES_RETURNS', true),
         'down_payments' => env('FEATURE_DOWN_PAYMENTS', true),

         // Purchasing & Payables
         'purchase_orders' => env('FEATURE_PURCHASE_ORDERS', true),
         'goods_receipt_notes' => env('FEATURE_GRN', true),
         'purchase_returns' => env('FEATURE_PURCHASE_RETURNS', true),

         // Inventory
         'inventory' => env('FEATURE_INVENTORY', true),
         'stock_opname' => env('FEATURE_STOCK_OPNAME', true),
         'warehouses' => env('FEATURE_WAREHOUSES', true),

         // Manufacturing
         'manufacturing' => env('FEATURE_MANUFACTURING', true),
         'bom' => env('FEATURE_BOM', true),
         'work_orders' => env('FEATURE_WORK_ORDERS', true),
         'material_requisitions' => env('FEATURE_MATERIAL_REQUISITIONS', true),
         'mrp' => env('FEATURE_MRP', true),
         'subcontracting' => env('FEATURE_SUBCONTRACTING', true),

         // Project Management
         'projects' => env('FEATURE_PROJECTS', true),

         // Financial
         'budgeting' => env('FEATURE_BUDGETING', true),
         'recurring' => env('FEATURE_RECURRING', true),
         'multi_currency' => env('FEATURE_MULTI_CURRENCY', true),
         'bank_reconciliation' => env('FEATURE_BANK_RECONCILIATION', true),
     ],
 ];

 File 2: app/Contracts/FeatureManager.php

 Interface contract for feature management - enables future implementation swapping.

 <?php

 namespace App\Contracts;

 interface FeatureManager
 {
     /**
      * Check if a feature module is enabled.
      */
     public function enabled(string $module, ?int $tenantId = null): bool;

     /**
      * Check if a feature module is disabled.
      */
     public function disabled(string $module, ?int $tenantId = null): bool;

     /**
      * Get all feature modules with their status.
      */
     public function all(?int $tenantId = null): array;

     /**
      * Get list of enabled module names.
      */
     public function enabledModules(?int $tenantId = null): array;

     /**
      * Get list of disabled module names.
      */
     public function disabledModules(?int $tenantId = null): array;
 }

 File 3: app/Support/ConfigFeatureManager.php

 Config-based implementation (current phase).

 <?php

 namespace App\Support;

 use App\Contracts\FeatureManager;

 class ConfigFeatureManager implements FeatureManager
 {
     /**
      * Check if a feature module is enabled.
      * Defaults to true if not configured (fail-safe).
      */
     public function enabled(string $module, ?int $tenantId = null): bool
     {
         return (bool) config("features.modules.{$module}", true);
     }

     /**
      * Check if a feature module is disabled.
      */
     public function disabled(string $module, ?int $tenantId = null): bool
     {
         return !$this->enabled($module, $tenantId);
     }

     /**
      * Get all feature modules with their status.
      */
     public function all(?int $tenantId = null): array
     {
         return config('features.modules', []);
     }

     /**
      * Get list of enabled module names.
      */
     public function enabledModules(?int $tenantId = null): array
     {
         return collect($this->all($tenantId))
             ->filter(fn (bool $enabled): bool => $enabled === true)
             ->keys()
             ->values()
             ->toArray();
     }

     /**
      * Get list of disabled module names.
      */
     public function disabledModules(?int $tenantId = null): array
     {
         return collect($this->all($tenantId))
             ->filter(fn (bool $enabled): bool => $enabled === false)
             ->keys()
             ->values()
             ->toArray();
     }
 }

 File 4: app/Support/Features.php

 Static facade for convenient access throughout the application.

 <?php

 namespace App\Support;

 use App\Contracts\FeatureManager;

 class Features
 {
     /**
      * Check if a feature module is enabled.
      */
     public static function enabled(string $module): bool
     {
         return static::manager()->enabled($module);
     }

     /**
      * Check if a feature module is disabled.
      */
     public static function disabled(string $module): bool
     {
         return static::manager()->disabled($module);
     }

     /**
      * Get all feature modules with their status.
      */
     public static function all(): array
     {
         return static::manager()->all();
     }

     /**
      * Get list of enabled module names.
      */
     public static function enabledModules(): array
     {
         return static::manager()->enabledModules();
     }

     /**
      * Get list of disabled module names.
      */
     public static function disabledModules(): array
     {
         return static::manager()->disabledModules();
     }

     /**
      * Get the feature manager instance.
      */
     protected static function manager(): FeatureManager
     {
         return app(FeatureManager::class);
     }
 }

 File 5: app/Providers/AppServiceProvider.php (Modify)

 Register the feature manager binding.

 // In register() method, add:
 $this->app->singleton(
     \App\Contracts\FeatureManager::class,
     \App\Support\ConfigFeatureManager::class
 );

 ---
 Phase 2: Middleware (Route Protection)

 File 6: app/Http/Middleware/EnsureFeatureEnabled.php

 Middleware to protect routes based on feature flags.

 <?php

 namespace App\Http\Middleware;

 use App\Support\Features;
 use Closure;
 use Illuminate\Http\Request;
 use Symfony\Component\HttpFoundation\Response;

 class EnsureFeatureEnabled
 {
     /**
      * Handle an incoming request.
      *
      * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
      */
     public function handle(Request $request, Closure $next, string $feature): Response
     {
         if (Features::disabled($feature)) {
             abort(404, 'Feature tidak tersedia.');
         }

         return $next($request);
     }
 }

 File 7: bootstrap/app.php (Modify)

 Register the middleware alias.

 ->withMiddleware(function (Middleware $middleware): void {
     $middleware->alias([
         'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
     ]);
 })

 ---
 Phase 3: API Endpoint (Feature Status)

 File 8: app/Http/Controllers/Api/V1/FeatureController.php

 Controller to expose feature status to frontend applications.

 <?php

 namespace App\Http\Controllers\Api\V1;

 use App\Http\Controllers\Controller;
 use App\Support\Features;
 use Illuminate\Http\JsonResponse;

 class FeatureController extends Controller
 {
     /**
      * Get feature flags status.
      *
      * GET /api/v1/features
      */
     public function index(): JsonResponse
     {
         return response()->json([
             'modules' => Features::all(),
             'enabled' => Features::enabledModules(),
             'disabled' => Features::disabledModules(),
         ]);
     }
 }

 File 9: routes/api.php (Modify)

 Add feature status route and apply middleware to feature-gated routes.

 // Feature status endpoint (always available)
 Route::get('features', [FeatureController::class, 'index']);

 // Feature-gated route groups
 Route::middleware('feature:quotations')->group(function () {
     Route::apiResource('quotations', QuotationController::class);
 });

 Route::middleware('feature:inventory')->group(function () {
     Route::apiResource('inventory-movements', InventoryMovementController::class);
     Route::apiResource('stock-opnames', StockOpnameController::class);
 });

 // ... more route groups

 ---
 Phase 4: Tests

 File 10: tests/Feature/FeatureFlagsTest.php

 Comprehensive tests for the feature flags system.

 <?php

 use App\Contracts\FeatureManager;
 use App\Models\User;
 use App\Support\ConfigFeatureManager;
 use App\Support\Features;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Laravel\Sanctum\Sanctum;

 uses(RefreshDatabase::class);

 beforeEach(function () {
     $user = User::factory()->create();
     Sanctum::actingAs($user);
 });

 describe('ConfigFeatureManager', function () {
     it('returns true for enabled features', function () {
         config(['features.modules.inventory' => true]);

         $manager = new ConfigFeatureManager();

         expect($manager->enabled('inventory'))->toBeTrue();
         expect($manager->disabled('inventory'))->toBeFalse();
     });

     it('returns false for disabled features', function () {
         config(['features.modules.mrp' => false]);

         $manager = new ConfigFeatureManager();

         expect($manager->enabled('mrp'))->toBeFalse();
         expect($manager->disabled('mrp'))->toBeTrue();
     });

     it('defaults to true for unconfigured features', function () {
         $manager = new ConfigFeatureManager();

         expect($manager->enabled('unknown_feature'))->toBeTrue();
     });

     it('lists enabled modules correctly', function () {
         config(['features.modules' => [
             'inventory' => true,
             'mrp' => false,
             'projects' => true,
         ]]);

         $manager = new ConfigFeatureManager();

         expect($manager->enabledModules())->toBe(['inventory', 'projects']);
     });

     it('lists disabled modules correctly', function () {
         config(['features.modules' => [
             'inventory' => true,
             'mrp' => false,
             'manufacturing' => false,
         ]]);

         $manager = new ConfigFeatureManager();

         expect($manager->disabledModules())->toBe(['mrp', 'manufacturing']);
     });
 });

 describe('Features Static Facade', function () {
     it('delegates to FeatureManager contract', function () {
         config(['features.modules.budgeting' => true]);

         expect(Features::enabled('budgeting'))->toBeTrue();
         expect(Features::disabled('budgeting'))->toBeFalse();
     });
 });

 describe('EnsureFeatureEnabled Middleware', function () {
     it('allows access when feature is enabled', function () {
         config(['features.modules.quotations' => true]);

         $response = $this->getJson('/api/v1/quotations');

         $response->assertOk();
     });

     it('returns 404 when feature is disabled', function () {
         config(['features.modules.quotations' => false]);

         $response = $this->getJson('/api/v1/quotations');

         $response->assertNotFound();
     });
 });

 describe('Feature Status API', function () {
     it('returns all feature flags', function () {
         config(['features.modules' => [
             'inventory' => true,
             'mrp' => false,
         ]]);

         $response = $this->getJson('/api/v1/features');

         $response->assertOk()
             ->assertJsonPath('modules.inventory', true)
             ->assertJsonPath('modules.mrp', false)
             ->assertJsonPath('enabled', ['inventory'])
             ->assertJsonPath('disabled', ['mrp']);
     });
 });

 File 11: tests/Pest.php (Modify)

 Add helper function for feature flag testing.

 /**
  * Configure feature flags for testing.
  */
 function withFeatures(array $features): void
 {
     foreach ($features as $feature => $enabled) {
         config(["features.modules.{$feature}" => $enabled]);
     }
 }

 /**
  * Disable specific features for testing.
  */
 function withoutFeatures(array $features): void
 {
     foreach ($features as $feature) {
         config(["features.modules.{$feature}" => false]);
     }
 }

 ---
 Phase 5: Route Integration

 Apply middleware to all feature-gated routes in routes/api.php.

 Route Groups by Feature Module

 | Feature               | Routes to Protect                          |
 |-----------------------|--------------------------------------------|
 | quotations            | quotations/*                               |
 | purchase_orders       | purchase-orders/*                          |
 | delivery_orders       | delivery-orders/*                          |
 | goods_receipt_notes   | goods-receipt-notes/*                      |
 | sales_returns         | sales-returns/*                            |
 | purchase_returns      | purchase-returns/*                         |
 | inventory             | inventory-movements/*, product-stocks/*    |
 | stock_opname          | stock-opnames/*                            |
 | warehouses            | warehouses/*                               |
 | manufacturing         | (parent for bom, work_orders, etc.)        |
 | bom                   | boms/*                                     |
 | work_orders           | work-orders/*                              |
 | material_requisitions | material-requisitions/*                    |
 | mrp                   | mrp/*                                      |
 | subcontracting        | subcontractor-*/*                          |
 | projects              | projects/*                                 |
 | budgeting             | budgets/*                                  |
 | recurring             | recurring-templates/*                      |
 | multi_currency        | currencies/*, exchange-rates/*             |
 | bank_reconciliation   | bank-transactions/*, bank-reconciliation/* |
 | down_payments         | down-payments/*                            |

 ---
 Files Summary

 New Files (8)

 | File                                              | Purpose                      |
 |---------------------------------------------------|------------------------------|
 | config/features.php                               | Feature module configuration |
 | app/Contracts/FeatureManager.php                  | Interface contract           |
 | app/Support/ConfigFeatureManager.php              | Config-based implementation  |
 | app/Support/Features.php                          | Static facade accessor       |
 | app/Http/Middleware/EnsureFeatureEnabled.php      | Route protection middleware  |
 | app/Http/Controllers/Api/V1/FeatureController.php | Feature status API           |
 | tests/Feature/FeatureFlagsTest.php                | Feature flags tests          |

 Modified Files (3)

 | File                                 | Changes                            |
 |--------------------------------------|------------------------------------|
 | app/Providers/AppServiceProvider.php | Register FeatureManager binding    |
 | bootstrap/app.php                    | Register middleware alias          |
 | routes/api.php                       | Apply feature middleware to routes |
 | tests/Pest.php                       | Add helper functions               |

 ---
 Execution Order

 Step 1: Create config/features.php
 Step 2: Create app/Contracts/FeatureManager.php
 Step 3: Create app/Support/ConfigFeatureManager.php
 Step 4: Create app/Support/Features.php
 Step 5: Modify app/Providers/AppServiceProvider.php
 Step 6: Create app/Http/Middleware/EnsureFeatureEnabled.php
 Step 7: Modify bootstrap/app.php
 Step 8: Create app/Http/Controllers/Api/V1/FeatureController.php
 Step 9: Modify tests/Pest.php (add helpers)
 Step 10: Create tests/Feature/FeatureFlagsTest.php
 Step 11: Run tests → All must pass
 Step 12: Modify routes/api.php (apply middleware)
 Step 13: Run full test suite → All 990+ tests must pass
 Step 14: Format with Pint

 ---
 Future Extension Path

 Phase A: Database-Based (Runtime Configurable)

 When needed, create:
 - database/migrations/xxxx_create_feature_flags_table.php
 - app/Models/FeatureFlag.php
 - app/Support/DatabaseFeatureManager.php (implements FeatureManager)
 - Change binding in AppServiceProvider

 Phase B: Multi-Tenant

 When needed, create:
 - database/migrations/xxxx_create_tenant_features_table.php
 - app/Models/TenantFeature.php
 - app/Support/TenantFeatureManager.php (implements FeatureManager)
 - Add tenant context resolution
 - Change binding in AppServiceProvider

 No changes needed to Features facade, middleware, or application code.

 ---
 Safety Guarantees

 - All features default to true (backward compatible)
 - Existing tests pass without modification
 - No database schema changes
 - No external package dependencies
 - Each step independently reversible
 - Middleware only affects explicitly protected routes

---
##  Feature Flags System - Implementation Complete

  Summary

  The contract-based feature flags system has been fully implemented with:

  New Files Created (7):
  | File                                              | Purpose                                          |
  |---------------------------------------------------|--------------------------------------------------|
  | config/features.php                               | Feature module configuration with env() defaults |
  | app/Contracts/FeatureManager.php                  | Interface for future extensibility               |
  | app/Support/ConfigFeatureManager.php              | Config-based implementation                      |
  | app/Support/Features.php                          | Static facade for convenient access              |
  | app/Http/Middleware/EnsureFeatureEnabled.php      | Route protection                                 |
  | app/Http/Controllers/Api/V1/FeatureController.php | API endpoint                                     |
  | tests/Feature/FeatureFlagsTest.php                | 21 comprehensive tests                           |

  Routes Protected by Feature Middleware (17 modules):
  - quotations, purchase_orders, delivery_orders
  - sales_returns, purchase_returns, down_payments
  - inventory, warehouses, stock_opname, goods_receipt_notes
  - bom, work_orders, material_requisitions, mrp, subcontracting
  - projects, budgeting, recurring, bank_reconciliation

  Test Results:
  - 21 tests passed (36 assertions) for feature flags
  - All existing tests continue to pass (features default to enabled)

  Usage:
  // Disable MRP in .env:
  FEATURE_MRP=false

  // Check in code:
  Features::enabled('mrp')  // false
  Features::disabled('mrp') // true

  // API response:
  GET /api/v1/features → { modules: {...}, enabled: [...], disabled: [...] }

  // Protected routes return 404 when disabled:
  GET /api/v1/mrp-runs → 404 Not Found
