<?php

namespace App\Support;

use App\Contracts\FeatureManager;

/**
 * Static facade for convenient feature flag access.
 *
 * Usage:
 *   Features::enabled('inventory')
 *   Features::disabled('mrp')
 *   Features::all()
 *   Features::enabledModules()
 *   Features::disabledModules()
 */
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
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return static::manager()->all();
    }

    /**
     * Get list of enabled module names.
     *
     * @return array<int, string>
     */
    public static function enabledModules(): array
    {
        return static::manager()->enabledModules();
    }

    /**
     * Get list of disabled module names.
     *
     * @return array<int, string>
     */
    public static function disabledModules(): array
    {
        return static::manager()->disabledModules();
    }

    /**
     * Get the feature manager instance from the container.
     */
    protected static function manager(): FeatureManager
    {
        return app(FeatureManager::class);
    }
}
