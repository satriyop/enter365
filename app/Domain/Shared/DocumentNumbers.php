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
        return DB::transaction(function () use ($prefix, $table, $column) {
            $last = DB::table($table)
                ->where($column, 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderBy($column, 'desc')
                ->value($column);

            $next = $last ? (int) substr($last, -4) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
