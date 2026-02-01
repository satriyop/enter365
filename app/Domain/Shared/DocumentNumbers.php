<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;

class DocumentNumbers
{
    public static function generate(
        string $prefix,
        string $table,
        string $column
    ): string {
        // Only use transaction if we're not already in one
        if (DB::transactionLevel() === 0) {
            return DB::transaction(function () use ($prefix, $table, $column) {
                return static::doGenerate($prefix, $table, $column);
            });
        }

        // We're already in a transaction, just generate without nested transaction
        return static::doGenerate($prefix, $table, $column);
    }

    private static function doGenerate(string $prefix, string $table, string $column): string
    {
        $query = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderBy($column, 'desc')
            ->lockForUpdate();

        $last = $query->value($column);

        $next = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
