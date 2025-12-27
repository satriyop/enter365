<?php

namespace App\Support;

use App\Contracts\FeatureManager;

/**
 * Config-based feature manager implementation.
 *
 * Reads feature flags from config/features.php which uses env() for configuration.
 * All features default to enabled (true) for backward compatibility.
 */
class ConfigFeatureManager implements FeatureManager
{
    /**
     * Check if a feature module is enabled.
     *
     * Defaults to true if not configured (fail-safe for backward compatibility).
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
        return ! $this->enabled($module, $tenantId);
    }

    /**
     * Get all feature modules with their status.
     *
     * @return array<string, bool>
     */
    public function all(?int $tenantId = null): array
    {
        return config('features.modules', []);
    }

    /**
     * Get list of enabled module names.
     *
     * @return array<int, string>
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
     *
     * @return array<int, string>
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
