<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use stdClass;

/**
 * Odoo analytic distribution is a map {analytic_account_id: percentage}.
 *
 * PHP arrays cannot keep numeric-string keys, and Laravel JsonResource
 * array_values()s any nested array whose keys are all numeric — so a stored
 * {"10":60,"20":40} is emitted as [60,40]. Encode maps as JSON objects.
 * A real JSON list (already collapsed in the database) is left as a list.
 *
 * @implements CastsAttributes<array<int|string, int|float>|list<int|float>|null, mixed>
 */
class AnalyticDistributionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                return null;
            }

            return $this->normalizeDecoded($decoded, ltrim($value)[0] ?? '');
        }

        if (is_object($value)) {
            return $this->normalizeDecoded((array) $value, '{');
        }

        if (is_array($value)) {
            return $this->normalizeDecoded($value, null);
        }

        return null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }

        $object = self::asObject($value);

        if ($object instanceof stdClass) {
            return [$key => json_encode($object, JSON_THROW_ON_ERROR)];
        }

        return [$key => json_encode($value, JSON_THROW_ON_ERROR)];
    }

    /**
     * Restore the original request map so store validation cannot persist a list.
     */
    public static function asObject(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof stdClass) {
            return $value;
        }

        if (! is_array($value) || array_is_list($value)) {
            return $value;
        }

        $map = new stdClass;
        foreach ($value as $accountId => $percentage) {
            $map->{(string) $accountId} = $percentage;
        }

        return $map;
    }

    /**
     * API payload: object for maps, list only when the value is already a list.
     */
    public static function forApi(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof stdClass) {
            return $value;
        }

        if (! is_array($value) || array_is_list($value)) {
            return $value;
        }

        return self::asObject($value);
    }

    /**
     * @param  array<mixed, mixed>  $decoded
     * @return array<int|string, mixed>|list<mixed>
     */
    private function normalizeDecoded(array $decoded, ?string $rawStart): array
    {
        if ($rawStart === '[') {
            return array_values($decoded);
        }

        if ($rawStart !== '{' && array_is_list($decoded)) {
            return array_values($decoded);
        }

        $map = [];
        foreach ($decoded as $accountId => $percentage) {
            $map[(string) $accountId] = $percentage;
        }

        return $map;
    }
}
