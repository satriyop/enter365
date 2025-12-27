<?php

namespace App\Contracts;

/**
 * Contract for feature flag management.
 *
 * This interface allows swapping implementations:
 * - ConfigFeatureManager: Config/env based (current)
 * - DatabaseFeatureManager: Database based (future)
 * - TenantFeatureManager: Multi-tenant based (future)
 */
interface FeatureManager
{
    /**
     * Check if a feature module is enabled.
     *
     * @param  string  $module  The feature module name (e.g., 'inventory', 'mrp')
     * @param  int|null  $tenantId  Optional tenant ID for multi-tenant support
     */
    public function enabled(string $module, ?int $tenantId = null): bool;

    /**
     * Check if a feature module is disabled.
     *
     * @param  string  $module  The feature module name
     * @param  int|null  $tenantId  Optional tenant ID for multi-tenant support
     */
    public function disabled(string $module, ?int $tenantId = null): bool;

    /**
     * Get all feature modules with their enabled/disabled status.
     *
     * @param  int|null  $tenantId  Optional tenant ID for multi-tenant support
     * @return array<string, bool>
     */
    public function all(?int $tenantId = null): array;

    /**
     * Get list of enabled module names.
     *
     * @param  int|null  $tenantId  Optional tenant ID for multi-tenant support
     * @return array<int, string>
     */
    public function enabledModules(?int $tenantId = null): array;

    /**
     * Get list of disabled module names.
     *
     * @param  int|null  $tenantId  Optional tenant ID for multi-tenant support
     * @return array<int, string>
     */
    public function disabledModules(?int $tenantId = null): array;
}
