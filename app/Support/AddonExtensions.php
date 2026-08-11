<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generic registry for optional product add-on extensions.
 *
 * Core ERP code merges resources / validation / eager-loads through this
 * bag without naming any industry pack. Add-on service providers register
 * callbacks when their feature flag is enabled.
 */
final class AddonExtensions
{
    /** @var array<string, list<callable(mixed): array<string, mixed>>> */
    private static array $resourceMappers = [];

    /** @var array<string, list<callable(): array<string, mixed>>> */
    private static array $validationRuleProviders = [];

    /** @var array<string, list<string>> */
    private static array $eagerLoads = [];

    /** @var array<string, list<callable(mixed): mixed>> */
    private static array $metaResolvers = [];

    /**
     * Register a resource attribute mapper for a core resource key.
     *
     * @param  callable(mixed): array<string, mixed>  $mapper
     */
    public static function registerResource(string $key, callable $mapper): void
    {
        self::$resourceMappers[$key][] = $mapper;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resourceAttributes(string $key, mixed $resource): array
    {
        $extra = [];

        foreach (self::$resourceMappers[$key] ?? [] as $mapper) {
            $chunk = $mapper($resource);
            if (is_array($chunk) && $chunk !== []) {
                $extra = array_merge($extra, $chunk);
            }
        }

        return $extra;
    }

    /**
     * Register validation rules provider for a form-request key.
     *
     * @param  callable(): array<string, mixed>  $provider
     */
    public static function registerValidationRules(string $key, callable $provider): void
    {
        self::$validationRuleProviders[$key][] = $provider;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $key): array
    {
        $rules = [];

        foreach (self::$validationRuleProviders[$key] ?? [] as $provider) {
            $chunk = $provider();
            if (is_array($chunk) && $chunk !== []) {
                $rules = array_merge($rules, $chunk);
            }
        }

        return $rules;
    }

    /**
     * Keys that add-ons own on validated payloads (stripped from core model fill).
     *
     * @return list<string>
     */
    public static function validationKeys(string $key): array
    {
        return array_keys(self::validationRules($key));
    }

    /**
     * Split validated input into core attributes vs add-on attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function splitValidated(string $key, array $validated): array
    {
        $addonKeys = self::validationKeys($key);
        $addon = [];

        foreach ($addonKeys as $addonKey) {
            if (array_key_exists($addonKey, $validated)) {
                $addon[$addonKey] = $validated[$addonKey];
                unset($validated[$addonKey]);
            }
        }

        return [$validated, $addon];
    }

    /**
     * @param  list<string>  $relations
     */
    public static function registerEagerLoads(string $key, array $relations): void
    {
        self::$eagerLoads[$key] = array_values(array_unique(array_merge(
            self::$eagerLoads[$key] ?? [],
            $relations
        )));
    }

    /**
     * @param  list<string>  $base
     * @return list<string>
     */
    public static function eagerLoads(string $key, array $base = []): array
    {
        return array_values(array_unique(array_merge($base, self::$eagerLoads[$key] ?? [])));
    }

    /**
     * @param  callable(mixed): mixed  $resolver
     */
    public static function registerMeta(string $key, callable $resolver): void
    {
        self::$metaResolvers[$key][] = $resolver;
    }

    public static function meta(string $key, mixed $subject, mixed $default = null): mixed
    {
        foreach (self::$metaResolvers[$key] ?? [] as $resolver) {
            return $resolver($subject);
        }

        return $default;
    }

    /**
     * Reset registries (tests).
     */
    public static function flush(): void
    {
        self::$resourceMappers = [];
        self::$validationRuleProviders = [];
        self::$eagerLoads = [];
        self::$metaResolvers = [];
    }
}
